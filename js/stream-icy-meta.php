<?php
header('Content-Type: application/json; charset=utf-8');

// 1. Configura la URL de tu streaming de radio aquí
$streamingUrl = 'https://stream.zeno.fm/mys646fkd2zuv'; 

echo json_encode(getIcyMetaData($streamingUrl));

/**
 * Función para conectar al streaming y extraer los metadatos ICY
 */
function getIcyMetaData($url) {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "Icy-MetaData: 1\r\n" // Indica al servidor que queremos metadatos
        ]
    ]);

    // Abrimos el flujo de streaming en modo lectura de bytes
    $stream = @fopen($url, 'r', false, $context);

    if (!$stream) {
        return ['error' => 'No se pudo conectar al streaming.'];
    }

    // Leemos las cabeceras de respuesta HTTP
    $metadataInterval = 0;
    $metaData = [];
    
    $headers = stream_get_meta_data($stream);
    if (isset($headers['wrapper_data'])) {
        foreach ($headers['wrapper_data'] as $header) {
            // Buscamos el intervalo (cada cuántos bytes de audio viene un bloque de texto)
            if (strpos(strtolower($header), 'icy-metaint:') !== false) {
                $parts = explode(':', $header);
                $metadataInterval = (int)trim($parts[1]);
                break;
            }
        }
    }

    // Si el servidor no soporta o no envió el intervalo, cerramos
    if ($metadataInterval <= 0) {
        fclose($stream);
        return ['error' => 'El servidor no proporciona metadatos ICY o el intervalo es inválido.'];
    }

    // 2. Saltamos los primeros bytes de audio puro hasta llegar al bloque de metadatos
    // Usamos un bucle por si la lectura se fragmenta
    $bytesLeidos = 0;
    while ($bytesLeidos < $metadataInterval && !feof($stream)) {
        $buffer = fread($stream, $metadataInterval - $bytesLeidos);
        if ($buffer === false) break;
        $bytesLeidos += strlen($buffer);
    }

    // 3. El primer byte después del intervalo indica el tamaño del bloque de metadatos (multiplicado por 16)
    $lengthByte = ord(fread($stream, 1));
    
    if ($lengthByte > 0) {
        $metaDataLength = $lengthByte * 16;
        $metaDataRaw = fread($stream, $metaDataLength);
        
        // Cerramos el flujo inmediatamente para no saturar el servidor ni consumir ancho de banda
        fclose($stream);

        // 4. Procesamos la cadena de texto extraída (Ej: StreamTitle='Artista - Canción';)
        if (preg_match('/StreamTitle=\'(.*?)\';/', $metaDataRaw, $matches)) {
            $streamTitle = $matches[1];
            
            // Intentamos separar el Artista y la Canción si están divididos por un guion
            $titleParts = explode(' - ', $streamTitle, 2);
            
            return [
                'status' => 'success',
                'stream_title' => $streamTitle,
                'artist' => isset($titleParts[0]) ? trim($titleParts[0]) : 'Desconocido',
                'song' => isset($titleParts[1]) ? trim($titleParts[1]) : trim($titleParts[0])
            ];
        }
        
        return ['error' => 'Metadatos encontrados pero el formato no incluye StreamTitle.', 'raw' => $metaDataRaw];
    }

    fclose($stream);
    return ['error' => 'No se encontraron datos en el bloque de metadatos actual (vacío).'];
}
