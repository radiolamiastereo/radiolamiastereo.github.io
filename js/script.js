const phpScriptUrl = 'stream-icy-meta.php?url=https://radiolamiastereo.github.io/';

async function obtenerMetadatos() {
    try {
        const respuesta = await fetch(phpScriptUrl);
        const datos = await respuesta.json(); // O .text() si el PHP no devuelve JSON
        
        console.log("Canción actual:", datos.title);
        // Aquí puedes actualizar el DOM de tu página
        document.getElementById('player-title').innerText = datos.title;
    } catch (error) {
        console.error("Error al obtener los metadatos:", error);
    }
}

// Llamar a la función cada 10 segundos para mantenerlo actualizado
setInterval(obtenerMetadatos, 10000);