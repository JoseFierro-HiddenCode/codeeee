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