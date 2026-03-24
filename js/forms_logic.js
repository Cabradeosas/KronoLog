/**
 * Alterna la visibilidad de un formulario usando clases para permitir transiciones
 * Además, alterna el icono entre mas.svg y signo-menos.svg
 */
function toggleForm(id, btn) {
    const section = document.getElementById(id);

    // Si queremos que solo haya un formulario abierto a la vez (cerrar los otros)
    document.querySelectorAll('.collapsible-content').forEach(c => {
        if (c.id !== id && c.classList.contains('show')) {
            c.classList.remove('show');
            // Intentar encontrar el botón asociado a ese contenido para resetear su icono
            // Esto asume que el botón tiene una referencia o que podemos encontrarlo
            // Una forma simple es buscar botones .btn-toggle que no sean el actual
            document.querySelectorAll('.btn-toggle').forEach(otherBtn => {
                if (otherBtn !== btn) {
                    otherBtn.classList.remove('active');
                    const otherImg = otherBtn.querySelector('img');
                    if (otherImg) {
                        otherImg.src = otherImg.src.replace('signo-menos.svg', 'mas.svg');
                    }
                }
            });
        }
    });

    section.classList.toggle('show');

    // Manejamos el estado del icono del botón actual
    const img = btn.querySelector('img');
    if (section.classList.contains('show')) {
        btn.classList.add('active');
        if (img) img.src = img.src.replace('mas.svg', 'signo-menos.svg');
    } else {
        btn.classList.remove('active');
        if (img) img.src = img.src.replace('signo-menos.svg', 'mas.svg');
    }
}

/**
 * Alterna el menú de navegación en dispositivos móviles
 */
function toggleMenu() {
    const nav = document.getElementById('main-nav') || document.querySelector('nav');
    if (nav) {
        nav.classList.toggle('show-mobile');
    }
}

/**
 * Carga dinámica de servicios al seleccionar un cliente en el formulario de horas
 * @param {HTMLSelectElement} clientSelect - El select del cliente
 * @param {string} serviceSelectId - El ID del select de servicios a rellenar
 */
async function loadClientServicesForHours(clientSelect, serviceSelectId) {
    const clientId = clientSelect.value;
    const serviceSelect = document.getElementById(serviceSelectId);

    // Resetear select
    serviceSelect.innerHTML = '<option value="" disabled selected>Cargando servicios...</option>';
    serviceSelect.disabled = true;

    if (!clientId) {
        serviceSelect.innerHTML = '<option value="" disabled selected>Selecciona un cliente primero</option>';
        return;
    }

    try {
        // Ajusta la ruta si tu archivo HTML no está en la raíz
        const response = await fetch(`../client/getServices.php?client_id=${clientId}&mode=hours`);
        if (!response.ok) throw new Error('Error en la respuesta del servidor');
        
        const services = await response.json();

        if (services.length === 0) {
            serviceSelect.innerHTML = '<option value="" disabled selected>No hay servicios asignados</option>';
            return;
        }

        let options = '<option value="" disabled selected>Selecciona un servicio</option>';
        services.forEach(service => {
            // service.id aquí es el client_service_id necesario para addHours.php
            options += `<option value="${service.id}">${service.name}</option>`;
        });
        serviceSelect.innerHTML = options;
        serviceSelect.disabled = false;
    } catch (error) {
        console.error('Error:', error);
        serviceSelect.innerHTML = '<option value="" disabled selected>Error al cargar</option>';
    }
}