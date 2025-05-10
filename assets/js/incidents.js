// Modal and Popup Management
function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
        // Apply blur to main content only
        const mainContent = document.querySelector('main');
        if (mainContent) {
            mainContent.classList.add('blurred');
        }
    }
}

function hideModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = '';
        // Remove blur from main content
        const mainContent = document.querySelector('main');
        if (mainContent) {
            mainContent.classList.remove('blurred');
        }
    }
}

function showPopup(type, message) {
    const popup = document.getElementById(`${type}-popup`);
    const overlay = document.getElementById('popup-overlay');
    const messageElement = popup.querySelector('.popup-message');
    const mainContent = document.querySelector('main');

    if (messageElement) {
        messageElement.textContent = message;
    }

    popup.classList.add('show');
    overlay.classList.add('show');
    mainContent.classList.add('blurred');

    // Start countdown for success popup
    if (type === 'success') {
        startCountdown();
    }
}

function hidePopup() {
    const popups = document.querySelectorAll('.popup');
    const overlay = document.getElementById('popup-overlay');
    const mainContent = document.querySelector('main');

    popups.forEach(popup => popup.classList.remove('show'));
    overlay.classList.remove('show');
    mainContent.classList.remove('blurred');
}

function startCountdown() {
    const countdownElement = document.querySelector('.popup-countdown');
    let count = 3;

    const interval = setInterval(() => {
        countdownElement.textContent = count;
        count--;

        if (count < 0) {
            clearInterval(interval);
            hidePopup();
            window.location.reload();
        }
    }, 1000);
}

// Form Handling
document.addEventListener('DOMContentLoaded', function() {
    // Add Incident Form
    const addIncidentForm = document.getElementById('add-form');
    if (addIncidentForm) {
        addIncidentForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('add', '1');

            try {
                const response = await fetch('incidents.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                if (data.success) {
                    hideModal('add-modal');
                    showPopup('success', data.message);
                } else {
                    showPopup('error', data.message);
                }
            } catch (error) {
                showPopup('error', 'An error occurred while processing your request.');
            }
        });
    }
    
    // Edit Incident Form
    const editIncidentForm = document.getElementById('edit-form');
    if (editIncidentForm) {
        editIncidentForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('update', '1');

            try {
                const response = await fetch('incidents.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                if (data.success) {
                    hideModal('edit-modal');
                    showPopup('success', data.message);
                } else {
                    showPopup('error', data.message);
                }
            } catch (error) {
                showPopup('error', 'An error occurred while processing your request.');
            }
        });
    }
    
    // Delete Incident Form
    const deleteIncidentForm = document.getElementById('delete-form');
    if (deleteIncidentForm) {
        deleteIncidentForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('delete', '1');

            try {
                const response = await fetch('incidents.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                if (data.success) {
                    hideModal('delete-modal');
                    showPopup('success', data.message);
                } else {
                    showPopup('error', data.message);
                }
            } catch (error) {
                showPopup('error', 'An error occurred while processing your request.');
            }
        });
    }
    
    // Search Functionality
    const searchInput = document.getElementById('searchInput');
    const searchOptions = document.getElementById('searchOptions');
    
    if (searchInput && searchOptions) {
        searchInput.addEventListener('focus', function() {
            searchOptions.classList.add('show');
        });
        
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !searchOptions.contains(e.target)) {
                searchOptions.classList.remove('show');
            }
        });
        
        const searchOptionButtons = searchOptions.querySelectorAll('.search-option');
        searchOptionButtons.forEach(button => {
            button.addEventListener('click', function() {
                const searchType = this.dataset.type;
                searchInput.placeholder = `Search by ${this.textContent.toLowerCase()}`;
                searchInput.dataset.searchType = searchType;
                searchOptions.classList.remove('show');
                searchInput.focus();
            });
        });
        
        searchInput.addEventListener('input', function() {
            const searchType = this.dataset.searchType || 'all';
            const searchValue = this.value.trim();
            
            if (searchValue.length > 0) {
                const formData = new FormData();
                formData.append('search', searchValue);
                formData.append('search_type', searchType);
                
                fetch('incidents.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.text())
                .then(html => {
                    const tableBody = document.querySelector('.table tbody');
                    if (tableBody) {
                        tableBody.innerHTML = html;
                    }
                })
                .catch(error => {
                    console.error('Search error:', error);
                });
            }
        });
    }
    
    // Close buttons
    const closeButtons = document.querySelectorAll('.modal-close, .popup-close');
    closeButtons.forEach(button => {
        button.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if (modal) {
                hideModal(modal.id);
            } else {
                hidePopup();
            }
        });
    });
    
    // Back button
    const backButton = document.getElementById('backButton');
    if (backButton) {
        backButton.addEventListener('click', function() {
            showPopup('cancel', 'Operation cancelled');
        });
    }
});
