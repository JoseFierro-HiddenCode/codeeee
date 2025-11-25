// ============================================
// THEME TOGGLE (DARK MODE)
// ============================================

function initThemeToggle() {
    const themeToggle = document.getElementById('theme-toggle');
    if (!themeToggle) return;
    
    const htmlElement = document.documentElement;
    const savedTheme = localStorage.getItem('theme') || 'light';
    htmlElement.setAttribute('data-theme', savedTheme);
    updateThemeIcon(savedTheme);
    
    themeToggle.addEventListener('click', () => {
        const currentTheme = htmlElement.getAttribute('data-theme');
        const newTheme = currentTheme === 'light' ? 'dark' : 'light';
        
        htmlElement.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        updateThemeIcon(newTheme);
    });
}

function updateThemeIcon(theme) {
    const icon = document.querySelector('#theme-toggle i');
    if (icon) {
        icon.className = theme === 'light' ? 'bi bi-moon-stars-fill' : 'bi bi-sun-fill';
    }
}

// ============================================
// DRAG & DROP KANBAN
// ============================================

let draggedElement = null;
let isDragging = false;
let dragStartTime = 0;

function initKanbanDragDrop() {
    const cards = document.querySelectorAll('.kanban-card[draggable="true"]');
    const columns = document.querySelectorAll('.kanban-column');
    
    if (cards.length === 0 || columns.length === 0) {
        console.log('ℹ️ No hay kanban en esta página');
        return;
    }
    
    cards.forEach(card => {
        // Drag & Drop
        card.addEventListener('dragstart', handleDragStart);
        card.addEventListener('dragend', handleDragEnd);
        
        // Click para abrir ticket
        card.addEventListener('click', handleCardClick);
        
        // Evitar que el click se active durante drag
        card.addEventListener('mousedown', handleMouseDown);
    });
    
    columns.forEach(column => {
        column.addEventListener('dragover', handleDragOver);
        column.addEventListener('drop', handleDrop);
        column.addEventListener('dragleave', handleDragLeave);
        column.addEventListener('dragenter', handleDragEnter);
    });
    
    console.log('✅ Kanban drag & drop inicializado');
}

function handleMouseDown(e) {
    isDragging = false;
    dragStartTime = Date.now();
}

function handleDragStart(e) {
    draggedElement = this;
    isDragging = true;
    this.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/html', this.innerHTML);
}

function handleDragEnd(e) {
    this.classList.remove('dragging');
    document.querySelectorAll('.kanban-column').forEach(col => {
        col.classList.remove('drag-over');
    });
    
    // Reset después de un delay para evitar click accidental
    setTimeout(() => {
        isDragging = false;
    }, 100);
}

function handleCardClick(e) {
    // Si se hizo drag, no abrir el ticket
    const clickDuration = Date.now() - dragStartTime;
    
    if (isDragging || clickDuration > 200) {
        return;
    }
    
    const ticketId = this.getAttribute('data-ticket-id');
    if (ticketId) {
        window.location.href = 'ver-ticket.php?id=' + ticketId;
    }
}

function handleDragEnter(e) {
    if (e.preventDefault) {
        e.preventDefault();
    }
    this.classList.add('drag-over');
}

function handleDragOver(e) {
    if (e.preventDefault) {
        e.preventDefault();
    }
    e.dataTransfer.dropEffect = 'move';
    return false;
}

function handleDragLeave(e) {
    if (e.target === this) {
        this.classList.remove('drag-over');
    }
}

function handleDrop(e) {
    if (e.stopPropagation) {
        e.stopPropagation();
    }
    if (e.preventDefault) {
        e.preventDefault();
    }
    
    this.classList.remove('drag-over');
    
    if (draggedElement && draggedElement !== this) {
        const ticketId = draggedElement.getAttribute('data-ticket-id');
        const newStatus = this.getAttribute('data-status');
        
        const targetColumn = this;
        const cardsContainer = targetColumn.querySelector('.kanban-cards-container');
        
        if (cardsContainer) {
            cardsContainer.appendChild(draggedElement);
        } else {
            const children = Array.from(targetColumn.children);
            const firstCard = children.find(child => child.classList.contains('kanban-card'));
            
            if (firstCard) {
                targetColumn.insertBefore(draggedElement, firstCard);
            } else {
                const header = targetColumn.querySelector('.kanban-header');
                if (header && header.nextSibling) {
                    targetColumn.insertBefore(draggedElement, header.nextSibling);
                } else {
                    targetColumn.appendChild(draggedElement);
                }
            }
        }
        
        updateKanbanCounts();
        updateTicketStatus(ticketId, newStatus);
    }
    
    return false;
}

function updateKanbanCounts() {
    document.querySelectorAll('.kanban-column').forEach(column => {
        const count = column.querySelectorAll('.kanban-card').length;
        const countBadge = column.querySelector('.kanban-count');
        if (countBadge) {
            countBadge.textContent = count;
        }
    });
}

function updateTicketStatus(ticketId, newStatus) {
    const formData = new FormData();
    formData.append('id', ticketId);
    formData.append('estado', newStatus);
    formData.append('ajax', '1');
    
    fetch('../actions/cambiar-estado.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(text => {
        try {
            const data = JSON.parse(text);
            
            if (data.success) {
                showNotification('✓ Ticket actualizado correctamente', 'success');
                updateTableRow(ticketId, newStatus);
            } else {
                showNotification('✗ Error: ' + (data.message || 'Desconocido'), 'danger');
                setTimeout(() => location.reload(), 2000);
            }
        } catch (e) {
            console.error('Error parseando JSON:', e);
            showNotification('✗ Error en la respuesta del servidor', 'danger');
            setTimeout(() => location.reload(), 2000);
        }
    })
    .catch(error => {
        console.error('Error AJAX:', error);
        showNotification('✗ Error de conexión', 'danger');
        setTimeout(() => location.reload(), 2000);
    });
}

function updateTableRow(ticketId, newStatus) {
    const rows = document.querySelectorAll('.ticket-row');
    
    rows.forEach(row => {
        const idCell = row.querySelector('td:first-child');
        if (idCell && idCell.textContent.includes('#' + ticketId)) {
            // Actualizar atributo data-estado
            row.setAttribute('data-estado', newStatus);
            
            // Encontrar la celda de estado (columna 7 generalmente)
            const cells = row.querySelectorAll('td');
            let estadoCellIndex = -1;
            
            // Buscar la celda que contiene un badge de estado
            cells.forEach((cell, index) => {
                if (cell.querySelector('.badge-status-abierto') || 
                    cell.querySelector('.badge-status-en_progreso') || 
                    cell.querySelector('.badge-status-cerrado')) {
                    estadoCellIndex = index;
                }
            });
            
            if (estadoCellIndex !== -1) {
                const badges = {
                    'abierto': '<span class="badge badge-status-abierto">Abierto</span>',
                    'en_progreso': '<span class="badge badge-status-en_progreso">En Progreso</span>',
                    'cerrado': '<span class="badge badge-status-cerrado">Cerrado</span>'
                };
                
                cells[estadoCellIndex].innerHTML = badges[newStatus] || newStatus;
            }
            
            // Si el ticket ahora está cerrado, agregar tiempo de cierre
            if (newStatus === 'cerrado') {
                // Opcional: actualizar la fecha a "Recién cerrado"
                const fechaCell = cells[cells.length - 2]; // Penúltima columna generalmente
                if (fechaCell) {
                    fechaCell.innerHTML = '<small style="color: var(--text-muted);">Recién cerrado</small>';
                }
            }
        }
    });
}
// ============================================
// FUNCIONES DE REABRIR/RESTAURAR TICKETS
// ============================================

// Reabrir ticket cerrado (<24h)
function reabrirTicket(ticketId) {
    if (!confirm('¿Reabrir este ticket?')) return;
    
    const formData = new FormData();
    formData.append('ticket_id', ticketId);
    formData.append('nuevo_estado', 'abierto');
    
    fetch('../actions/cambiar-estado-ticket.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('✓ Ticket reabierto correctamente', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification('✗ Error: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('✗ Error de conexión', 'danger');
    });
}

// Restaurar ticket archivado (solo admin)
function restaurarTicket(ticketId) {
    if (!confirm('¿Restaurar este ticket archivado? Volverá a aparecer en el dashboard.')) return;
    
    const formData = new FormData();
    formData.append('ticket_id', ticketId);
    
    fetch('../actions/restaurar-ticket.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('✓ Ticket restaurado correctamente', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification('✗ Error: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('✗ Error de conexión', 'danger');
    });
}

// ============================================
// NOTIFICATIONS
// ============================================

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} notification`;
    notification.innerHTML = `
        <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-triangle' : 'info-circle'}-fill"></i>
        ${message}
    `;

    document.body.appendChild(notification);

    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}
// ============================================
// INITIALIZE ALL
// ============================================

document.addEventListener('DOMContentLoaded', () => {
    initThemeToggle();
    initKanbanDragDrop();
    
    console.log('✅ Sistema inicializado correctamente');
});

// ============================================
// HELPER FUNCTIONS
// ============================================

function filtrarTickets(estado) {
    const rows = document.querySelectorAll('.ticket-row');
    
    rows.forEach(row => {
        if (estado === 'todos') {
            row.style.display = '';
        } else if (estado === 'sin_asignar') {
            row.style.display = row.dataset.asignado === 'no' ? '' : 'none';
        } else if (estado === 'urgente') {
            row.style.display = row.dataset.prioridad === 'urgente' ? '' : 'none';
        } else {
            row.style.display = row.dataset.estado === estado ? '' : 'none';
        }
    });
}


// Auto-asignación para admin
function asignarmeAMi() {
    const ticketId = document.getElementById('ticketIdAsignar').value;
    
    if (!ticketId) {
        mostrarNotificacion('Error: No se pudo obtener el ID del ticket', 'error');
        return;
    }

    if (!confirm('¿Deseas asignarte este ticket a ti mismo?')) {
        return;
    }

    const formData = new FormData();
    formData.append('ticket_id', ticketId);
    formData.append('auto_asignar', 'true');

    fetch('../actions/asignar-tecnico.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            mostrarNotificacion(data.message, 'success');
            
            // Cerrar modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('modalAsignarTecnico'));
            if (modal) modal.hide();
            
            // Recargar página después de 1 segundo
            setTimeout(() => location.reload(), 1000);
        } else {
            mostrarNotificacion(data.message || 'Error al asignar ticket', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarNotificacion('Error de conexión', 'error');
    });
}

// ====================================================
// CAMBIAR ESTADO DE TICKET (CON ACTUALIZACIÓN INSTANTÁNEA)
// ====================================================
function cambiarEstadoTicket(ticketId, nuevoEstado) {
    // Textos según el estado
    const mensajes = {
        'en_progreso': '¿Marcar este ticket como "En Progreso"?',
        'cerrado': '¿Cerrar este ticket? Esta acción marcará el ticket como resuelto.'
    };
    
    const confirmacion = confirm(mensajes[nuevoEstado] || '¿Cambiar el estado del ticket?');
    
    if (!confirmacion) {
        return;
    }
    
    const formData = new FormData();
    formData.append('ticket_id', ticketId);
    formData.append('nuevo_estado', nuevoEstado);
    
    fetch('../actions/cambiar-estado.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            mostrarNotificacion(data.message, 'success');
            
            // ACTUALIZACIÓN INSTANTÁNEA: Actualizar badge de estado
            actualizarBadgeEstado(nuevoEstado);
            
            // Recargar después de 1.5 segundos para actualizar botones
            setTimeout(() => location.reload(), 1500);
        } else {
            mostrarNotificacion(data.message || 'Error al cambiar estado', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarNotificacion('Error de conexión', 'error');
    });
}

// ====================================================
// ACTUALIZAR BADGE DE ESTADO INSTANTÁNEAMENTE
// ====================================================
function actualizarBadgeEstado(nuevoEstado) {
    const badge = document.querySelector('.card-header .badge');
    
    if (badge) {
        // Remover clases anteriores
        badge.classList.remove('bg-primary', 'bg-warning', 'bg-success');
        
        // Agregar clase según nuevo estado
        if (nuevoEstado === 'abierto') {
            badge.classList.add('bg-primary');
            badge.textContent = 'Abierto';
        } else if (nuevoEstado === 'en_progreso') {
            badge.classList.add('bg-warning');
            badge.textContent = 'En Progreso';
        } else if (nuevoEstado === 'cerrado') {
            badge.classList.add('bg-success');
            badge.textContent = 'Cerrado';
        }
    }
}

// ====================================================
// ARCHIVAR TICKET
// ====================================================
function archivarTicket(ticketId) {
    const confirmacion = confirm(
        '¿Archivar este ticket?\n\n' +
        'El ticket se moverá a la sección de archivados y no aparecerá en el dashboard principal.'
    );
    
    if (!confirmacion) {
        return;
    }
    
    const formData = new FormData();
    formData.append('ticket_id', ticketId);
    
    fetch('../actions/archivar-ticket.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            mostrarNotificacion(data.message, 'success');
            
            // Redirigir al dashboard después de 1.5 segundos
            setTimeout(() => {
                window.location.href = 'dashboard-admin.php';
            }, 1500);
        } else {
            mostrarNotificacion(data.message || 'Error al archivar ticket', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarNotificacion('Error de conexión', 'error');
    });
}

// ====================================================
// CAMBIAR ESTADO DE TICKET (CON ACTUALIZACIÓN INSTANTÁNEA)
// ====================================================
function cambiarEstadoTicket(ticketId, nuevoEstado) {
    // Textos según el estado
    const mensajes = {
        'en_progreso': '¿Marcar este ticket como "En Progreso"?',
        'cerrado': '¿Cerrar este ticket? Esta acción marcará el ticket como resuelto.'
    };
    
    const confirmacion = confirm(mensajes[nuevoEstado] || '¿Cambiar el estado del ticket?');
    
    if (!confirmacion) {
        return;
    }
    
    const formData = new FormData();
    formData.append('ticket_id', ticketId);
    formData.append('nuevo_estado', nuevoEstado);
    
    fetch('../actions/cambiar-estado-ticket.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            mostrarNotificacion(data.message, 'success');
            
            // ACTUALIZACIÓN INSTANTÁNEA: Actualizar badge de estado
            actualizarBadgeEstado(nuevoEstado);
            
            // Recargar después de 1.5 segundos para actualizar botones
            setTimeout(() => location.reload(), 1500);
        } else {
            mostrarNotificacion(data.message || 'Error al cambiar estado', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarNotificacion('Error de conexión', 'error');
    });
}

// ====================================================
// ACTUALIZAR BADGE DE ESTADO INSTANTÁNEAMENTE
// ====================================================
function actualizarBadgeEstado(nuevoEstado) {
    // Buscar el badge en el header de "Acciones del Ticket"
    const badgeAcciones = document.querySelector('.card-header .badge');
    
    if (badgeAcciones) {
        // Remover clases anteriores
        badgeAcciones.classList.remove('bg-primary', 'bg-warning', 'bg-success');
        
        // Agregar clase según nuevo estado
        if (nuevoEstado === 'abierto') {
            badgeAcciones.classList.add('bg-primary');
            badgeAcciones.textContent = 'Abierto';
        } else if (nuevoEstado === 'en_progreso') {
            badgeAcciones.classList.add('bg-warning');
            badgeAcciones.textContent = 'En Progreso';
        } else if (nuevoEstado === 'cerrado') {
            badgeAcciones.classList.add('bg-success');
            badgeAcciones.textContent = 'Cerrado';
        }
    }
    
    // También actualizar el badge en el breadcrumb/header principal
    const badges = document.querySelectorAll('.badge-status-abierto, .badge-status-en_progreso, .badge-status-cerrado');
    badges.forEach(badge => {
        badge.classList.remove('badge-status-abierto', 'badge-status-en_progreso', 'badge-status-cerrado');
        badge.classList.add('badge-status-' + nuevoEstado);
        badge.textContent = nuevoEstado.replace('_', ' ').toUpperCase();
    });
}

// ====================================================
// ARCHIVAR TICKET
// ====================================================
function archivarTicket(ticketId) {
    const confirmacion = confirm(
        '¿Archivar este ticket?\n\n' +
        'El ticket se moverá a la sección de archivados y no aparecerá en el dashboard principal.'
    );
    
    if (!confirmacion) {
        return;
    }
    
    const formData = new FormData();
    formData.append('ticket_id', ticketId);
    
    fetch('../actions/archivar-ticket.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            mostrarNotificacion(data.message, 'success');
            
            // Redirigir al dashboard después de 1.5 segundos
            setTimeout(() => {
                // Detectar dashboard según rol (simplificado, asume que estás en la carpeta correcta)
                window.location.href = 'dashboard-admin.php';
            }, 1500);
        } else {
            mostrarNotificacion(data.message || 'Error al archivar ticket', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarNotificacion('Error de conexión', 'error');
    });
}

// ====================================================
// ARCHIVAR DESDE TABLA (dashboard-admin.php)
// ====================================================
function archivarTicketDesdeTabla(ticketId) {
    const confirmacion = confirm('¿Archivar este ticket?');
    
    if (!confirmacion) {
        return;
    }
    
    const formData = new FormData();
    formData.append('ticket_id', ticketId);
    
    fetch('../actions/archivar-ticket.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            mostrarNotificacion(data.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            mostrarNotificacion(data.message || 'Error al archivar', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarNotificacion('Error de conexión', 'error');
    });
}

