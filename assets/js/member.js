document.addEventListener('DOMContentLoaded', () => {
    // Element references
    const elements = {
        editModal: document.getElementById('edit-modal'),
        editDetailsModal: document.getElementById('edit-details-modal'),
        editFamilyModal: document.getElementById('edit-family-modal'),
        deleteModal: document.getElementById('delete-modal'),
        overlay: document.getElementById('overlay'),
        successPopup: document.getElementById('success-popup'),
        errorPopup: document.getElementById('error-popup'),
        cancelPopup: document.getElementById('cancel-popup'),
        successMessage: document.getElementById('success-message'),
        errorMessage: document.getElementById('error-message'),
        deleteIdInput: document.getElementById('delete-id'),
        editForm: document.getElementById('edit-form'),
        editDetailsForm: document.getElementById('edit-details-form'),
        editFamilyForm: document.getElementById('edit-family-form'),
        deleteForm: document.getElementById('delete-form'),
        searchForm: document.getElementById('search-form'),
        searchInput: document.getElementById('search-input'),
        clearSearch: document.getElementById('clear-search'),
        searchLoading: document.getElementById('search-loading'),
    };

    // Utility functions
    const showModal = (modal) => {
        if (!modal) return;
        modal.classList.add('show');
        elements.overlay.classList.add('show');
        document.body.style.overflow = 'hidden';
        document.body.style.position = 'fixed';
        document.body.style.width = '100%';
        modal.style.filter = 'none';
    };

    const hideModal = (modal) => {
        if (!modal) return;
        modal.classList.remove('show');
        elements.overlay.classList.remove('show');
        document.body.style.overflow = '';
        document.body.style.position = '';
        document.body.style.width = '';
    };

    const showPopup = (popup, message) => {
        if (!popup) return;
        if (message && popup === elements.errorPopup) {
            elements.errorMessage.textContent = message;
        }
        elements.overlay.classList.add('show');
        popup.classList.add('show');
        document.body.style.overflow = 'hidden';
        document.body.style.position = 'fixed';
        document.body.style.width = '100%';
    };

    const hidePopup = (popup) => {
        if (!popup) return;
        elements.overlay.classList.remove('show');
        popup.classList.remove('show');
        document.body.style.overflow = '';
        document.body.style.position = '';
        document.body.style.width = '';
    };

    const startCountdown = (elementId, redirectUrl) => {
        let timeLeft = 3;
        const countdown = document.getElementById(elementId);
        if (countdown) {
            countdown.textContent = timeLeft;
            const interval = setInterval(() => {
                timeLeft--;
                countdown.textContent = timeLeft;
                if (timeLeft <= 0) {
                    clearInterval(interval);
                    hidePopup(document.querySelector('.popup.show'));
                    if (redirectUrl) {
                        window.location.replace(redirectUrl);
                    }
                }
            }, 1000);
        }
    };

    // Event listeners
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', () => {
            try {
                const member = JSON.parse(button.getAttribute('data-member') || '{}');
                const fields = {
                    'edit-id': member.id,
                    'edit-full_name': member.full_name,
                    'edit-contact_number': member.contact_number,
                    'edit-membership_type': member.membership_type || 'Individual',
                    'edit-payment_status': member.payment_status || 'Active',
                    'edit-member_status': member.member_status || 'Active',
                };
                Object.entries(fields).forEach(([id, value]) => {
                    const element = document.getElementById(id);
                    if (element) element.value = value || '';
                });
                showModal(elements.editModal);
            } catch (e) {
                console.error('Failed to parse member data:', e);
                showPopup(elements.errorPopup, 'Error loading member data. Please try again.');
                startCountdown('error-countdown', null);
            }
        });
    });

    document.querySelectorAll('.edit-details-btn').forEach(button => {
        button.addEventListener('click', () => {
            try {
                const member = JSON.parse(button.getAttribute('data-member') || '{}');
                const fields = {
                    'edit-details-id': member.id,
                    'edit-details-full_name': member.full_name,
                    'edit-details-date_of_birth': member.date_of_birth,
                    'edit-details-gender': member.gender || 'Male',
                    'edit-details-nic_number': member.nic_number,
                    'edit-details-address': member.address,
                    'edit-details-contact_number': member.contact_number,
                    'edit-details-email': member.email,
                    'edit-details-date_of_joining': member.date_of_joining,
                    'edit-details-membership_type': member.membership_type || 'Individual',
                    'edit-details-payment_status': member.payment_status || 'Active',
                    'edit-details-member_status': member.member_status || 'Active',
                };
                Object.entries(fields).forEach(([id, value]) => {
                    const element = document.getElementById(id);
                    if (element) element.value = value || '';
                });
                showModal(elements.editDetailsModal);
            } catch (e) {
                console.error('Failed to parse member data:', e);
                showPopup(elements.errorPopup, 'Error loading member data. Please try again.');
                startCountdown('error-countdown', null);
            }
        });
    });

    document.querySelectorAll('.edit-family-btn').forEach(button => {
        button.addEventListener('click', () => {
            try {
                const family = JSON.parse(button.getAttribute('data-family') || '{}');
                const memberId = button.getAttribute('data-member-id');
                const fields = {
                    'edit-family-member-id': memberId,
                    'edit-spouse-name': family.spouse?.name || '',
                    'edit-spouse-dob': family.spouse?.dob || '',
                    'edit-spouse-gender': family.spouse?.gender || '',
                };
                Object.entries(fields).forEach(([id, value]) => {
                    const element = document.getElementById(id);
                    if (element) element.value = value || '';
                });

                const childrenContainer = document.getElementById('children-container');
                const dependentsContainer = document.getElementById('dependents-container');
                childrenContainer.innerHTML = '';
                dependentsContainer.innerHTML = '';

                if (family.children?.length > 0) {
                    family.children.forEach(child => {
                        addDynamicField(childrenContainer, 'child', child);
                    });
                } else {
                    addDynamicField(childrenContainer, 'child');
                }

                if (family.dependents?.length > 0) {
                    family.dependents.forEach(dependent => {
                        addDynamicField(dependentsContainer, 'dependent', dependent);
                    });
                } else {
                    addDynamicField(dependentsContainer, 'dependent');
                }

                showModal(elements.editFamilyModal);
            } catch (e) {
                console.error('Failed to parse family data:', e);
                showPopup(elements.errorPopup, 'Error loading family data. Please try again.');
                startCountdown('error-countdown', null);
            }
        });
    });

    const addDynamicField = (container, type, data = {}) => {
        if (!container) return;
        const fieldDiv = document.createElement('div');
        fieldDiv.className = 'form-group dynamic-field';

        if (type === 'child') {
            fieldDiv.innerHTML = `
                <div class="grid">
                    <div class="form-group">
                        <input type="text" name="children[][name]" class="input-field" value="${data.name || ''}" placeholder="Child Name">
                    </div>
                    <div class="form-group">
                        <input type="date" name="children[][dob]" class="input-field date-picker" value="${data.dob || ''}" max="${new Date().toISOString().split('T')[0]}">
                    </div>
                    <div class="form-group">
                        <select name="children[][gender]" class="input-field">
                            <option value="">Select Gender</option>
                            <option value="Male" ${data.gender === 'Male' ? 'selected' : ''}>Male</option>
                            <option value="Female" ${data.gender === 'Female' ? 'selected' : ''}>Female</option>
                            <option value="Other" ${data.gender === 'Other' ? 'selected' : ''}>Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <button type="button" class="btn-icon remove-field"><i class="ri-delete-bin-line"></i></button>
                    </div>
                </div>
            `;
        } else if (type === 'dependent') {
            fieldDiv.innerHTML = `
                <div class="grid">
                    <div class="form-group">
                        <input type="text" name="dependents[][name]" class="input-field" value="${data.name || ''}" placeholder="Dependent Name">
                    </div>
                    <div class="form-group">
                        <select name="dependents[][relationship]" class="input-field">
                            <option value="">Select Relationship</option>
                            <option value="Father" ${data.relationship === 'Father' ? 'selected' : ''}>Father</option>
                            <option value="Mother" ${data.relationship === 'Mother' ? 'selected' : ''}>Mother</option>
                            <option value="Spouse's Father" ${data.relationship === "Spouse's Father" ? 'selected' : ''}>Spouse's Father</option>
                            <option value="Spouse's Mother" ${data.relationship === "Spouse's Mother" ? 'selected' : ''}>Spouse's Mother</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <input type="date" name="dependents[][dob]" class="input-field date-picker" value="${data.dob || ''}" max="${new Date().toISOString().split('T')[0]}">
                    </div>
                    <div class="form-group">
                        <input type="text" name="dependents[][address]" class="input-field" value="${data.address || ''}" placeholder="Address">
                    </div>
                    <div class="form-group">
                        <button type="button" class="btn-icon remove-field"><i class="ri-delete-bin-line"></i></button>
                    </div>
                </div>
            `;
        }

        container.appendChild(fieldDiv);
        fieldDiv.querySelector('.remove-field').addEventListener('click', () => {
            if (container.children.length > 1) {
                fieldDiv.remove();
            }
        });
    };

    document.getElementById('add-child')?.addEventListener('click', () => {
        addDynamicField(document.getElementById('children-container'), 'child');
    });

    document.getElementById('add-dependent')?.addEventListener('click', () => {
        addDynamicField(document.getElementById('dependents-container'), 'dependent');
    });

    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', () => {
            const id = button.getAttribute('data-id');
            if (elements.deleteIdInput) {
                elements.deleteIdInput.value = id;
                showModal(elements.deleteModal);
            }
        });
    });

    document.querySelectorAll('.modal-close').forEach(button => {
        button.addEventListener('click', () => {
            const modal = button.closest('.modal');
            if (modal) {
                hideModal(modal);
                // Only show cancel popup if explicitly needed
                // Commenting out to prevent automatic cancel popup
                // showPopup(elements.cancelPopup);
                // startCountdown('cancel-countdown', 'members.php');
            }
        });
    });

    document.querySelectorAll('.accordion-header').forEach(header => {
        header.addEventListener('click', () => {
            const targetId = header.getAttribute('data-target');
            const content = document.getElementById(targetId);
            if (!content) return;
            const isActive = content.classList.contains('active');
            document.querySelectorAll('.accordion-content').forEach(c => c.classList.remove('active'));
            if (!isActive) {
                content.classList.add('active');
            }
        });
    });

    const handleFormSubmission = async (form, modal, action) => {
        const formData = new FormData(form);
        formData.append(action, 'true');

        try {
            const response = await fetch('', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (!response.ok) throw new Error('Network response was not ok');
            const result = await response.json();

            if (result.success) {
                hideModal(modal);
                showPopup(elements.successPopup);
                startCountdown('success-countdown', 'members.php');
            } else {
                showPopup(elements.errorPopup, result.message || 'Operation failed');
                startCountdown('error-countdown', null);
            }
        } catch (error) {
            console.error(`Error submitting ${action} form:`, error);
            showPopup(elements.errorPopup, 'An unexpected error occurred. Please try again.');
            startCountdown('error-countdown', null);
        }
    };

    elements.editForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const contactNumber = document.getElementById('edit-contact_number')?.value;
        if (!/^\+94\d{9}$/.test(contactNumber)) {
            document.getElementById('edit-contact_number-error')?.classList.add('show');
            return;
        }
        await handleFormSubmission(elements.editForm, elements.editModal, 'update');
    });

    elements.editDetailsForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const contactNumber = document.getElementById('edit-details-contact_number')?.value;
        if (!/^\+94\d{9}$/.test(contactNumber)) {
            document.getElementById('edit-details-contact_number-error')?.classList.add('show');
            return;
        }
        await handleFormSubmission(elements.editDetailsForm, elements.editDetailsModal, 'update_details');
    });

    elements.editFamilyForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        await handleFormSubmission(elements.editFamilyForm, elements.editFamilyModal, 'update_family');
    });

    elements.deleteForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        await handleFormSubmission(elements.deleteForm, elements.deleteModal, 'delete');
    });

    elements.overlay?.addEventListener('click', () => {
        [elements.editModal, elements.editDetailsModal, elements.editFamilyModal, elements.deleteModal].forEach(modal => {
            if (modal?.classList.contains('show')) {
                hideModal(modal);
            }
        });
        [elements.successPopup, elements.errorPopup, elements.cancelPopup].forEach(popup => {
            if (popup?.classList.contains('show')) {
                hidePopup(popup);
            }
        });
    });

    elements.clearSearch?.addEventListener('click', () => {
        if (elements.searchInput) {
            elements.searchInput.value = '';
            elements.searchForm?.submit();
        }
    });
});