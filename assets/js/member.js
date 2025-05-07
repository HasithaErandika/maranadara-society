document.addEventListener('DOMContentLoaded', () => {
    const editModal = document.getElementById('edit-modal');
    const editDetailsModal = document.getElementById('edit-details-modal');
    const editFamilyModal = document.getElementById('edit-family-modal');
    const deleteModal = document.getElementById('delete-modal');
    const overlay = document.getElementById('overlay');
    const editButtons = document.querySelectorAll('.edit-btn');
    const editDetailsButtons = document.querySelectorAll('.edit-details-btn');
    const editFamilyButtons = document.querySelectorAll('.edit-family-btn');
    const deleteButtons = document.querySelectorAll('.delete-btn');
    const closeButtons = document.querySelectorAll('.modal-close');
    const accordionHeaders = document.querySelectorAll('.accordion-header');
    const searchInput = document.getElementById('search-input');
    const clearSearch = document.getElementById('clear-search');
    const editForm = document.getElementById('edit-form');
    const editDetailsForm = document.getElementById('edit-details-form');
    const editFamilyForm = document.getElementById('edit-family-form');
    const deleteForm = document.getElementById('delete-form');
    const searchForm = document.getElementById('search-form');
    const searchLoading = document.getElementById('search-loading');
    const successPopup = document.getElementById('success-popup');
    const errorPopup = document.getElementById('error-popup');
    const cancelPopup = document.getElementById('cancel-popup');
    const successMessage = document.getElementById('success-message');
    const errorMessage = document.getElementById('error-message');

    // Show popups if messages exist
    const successMsg = window.successMsg;
    const errorMsg = window.errorMsg;

    if (successMsg && successMsg !== '') {
        successMessage.textContent = successMsg;
        showPopup(successPopup);
        startCountdown('success-countdown', window.location.href);
    } else if (errorMsg && errorMsg !== '') {
        errorMessage.textContent = errorMsg;
        showPopup(errorPopup);
        startCountdown('error-countdown', window.location.href);
    }

    function showPopup(popup) {
        overlay.classList.add('show');
        popup.classList.add('show');
        document.body.style.overflow = 'hidden';
        document.body.style.position = 'fixed';
        document.body.style.width = '100%';
    }

    function hidePopup(popup) {
        overlay.classList.remove('show');
        popup.classList.remove('show');
        document.body.style.overflow = '';
        document.body.style.position = '';
        document.body.style.width = '';
    }

    function startCountdown(elementId, redirectUrl) {
        let timeLeft = 3;
        const countdown = document.getElementById(elementId);
        if (countdown) {
            const interval = setInterval(() => {
                timeLeft--;
                countdown.textContent = timeLeft;
                if (timeLeft <= 0) {
                    clearInterval(interval);
                    if (redirectUrl) {
                        window.location.href = redirectUrl;
                    }
                }
            }, 1000);
        }
    }

    function showModal(modal) {
        modal.classList.add('show');
        overlay.classList.add('show');
        document.body.style.overflow = 'hidden';
        document.body.style.position = 'fixed';
        document.body.style.width = '100%';
    }

    function hideModal(modal) {
        modal.classList.remove('show');
        overlay.classList.remove('show');
        document.body.style.overflow = '';
        document.body.style.position = '';
        document.body.style.width = '';
    }

    // Edit modal (Manage Members)
    editButtons.forEach(button => {
        button.addEventListener('click', () => {
            try {
                const member = JSON.parse(button.getAttribute('data-member') || '{}');
                document.getElementById('edit-id').value = member.id || '';
                document.getElementById('edit-full_name').value = member.full_name || '';
                document.getElementById('edit-contact_number').value = member.contact_number || '';
                document.getElementById('edit-membership_type').value = member.membership_type || 'Individual';
                document.getElementById('edit-payment_status').value = member.payment_status || 'Active';
                document.getElementById('edit-member_status').value = member.member_status || 'Active';
                showModal(editModal);
            } catch (e) {
                console.error('Failed to parse member data:', e);
                errorMessage.textContent = 'Error loading member data. Please try again.';
                showPopup(errorPopup);
            }
        });
    });

    // Edit details modal (Member Details)
    editDetailsButtons.forEach(button => {
        button.addEventListener('click', () => {
            try {
                const member = JSON.parse(button.getAttribute('data-member') || '{}');
                document.getElementById('edit-details-id').value = member.id || '';
                document.getElementById('edit-details-full_name').value = member.full_name || '';
                document.getElementById('edit-details-date_of_birth').value = member.date_of_birth || '';
                document.getElementById('edit-details-gender').value = member.gender || 'Male';
                document.getElementById('edit-details-nic_number').value = member.nic_number || '';
                document.getElementById('edit-details-address').value = member.address || '';
                document.getElementById('edit-details-contact_number').value = member.contact_number || '';
                document.getElementById('edit-details-email').value = member.email || '';
                document.getElementById('edit-details-date_of_joining').value = member.date_of_joining || '';
                document.getElementById('edit-details-membership_type').value = member.membership_type || 'Individual';
                document.getElementById('edit-details-payment_status').value = member.payment_status || 'Active';
                document.getElementById('edit-details-member_status').value = member.member_status || 'Active';
                showModal(editDetailsModal);
            } catch (e) {
                console.error('Failed to parse member data:', e);
                errorMessage.textContent = 'Error loading member data. Please try again.';
                showPopup(errorPopup);
            }
        });
    });

    // Edit family modal
    editFamilyButtons.forEach(button => {
        button.addEventListener('click', () => {
            try {
                const family = JSON.parse(button.getAttribute('data-family') || '{}');
                const memberId = button.getAttribute('data-member-id');
                document.getElementById('edit-family-member-id').value = memberId || '';
                
                // Set spouse information
                document.getElementById('edit-spouse-name').value = family.spouse_name || '';
                document.getElementById('edit-spouse-age').value = family.spouse_age || '';
                document.getElementById('edit-spouse-gender').value = family.spouse_gender || '';

                const childrenContainer = document.getElementById('children-container');
                const dependentsContainer = document.getElementById('dependents-container');
                childrenContainer.innerHTML = '';
                dependentsContainer.innerHTML = '';

                // Handle children
                if (family.children && family.children.length > 0) {
                    family.children.forEach(child => {
                        const fieldDiv = document.createElement('div');
                        fieldDiv.className = 'form-group dynamic-field';
                        fieldDiv.innerHTML = `
                            <div class="grid">
                                <div class="form-group">
                                    <input type="text" name="children[][name]" class="input-field" value="${child.name || ''}" placeholder="Child Name">
                                </div>
                                <div class="form-group">
                                    <input type="number" name="children[][age]" class="input-field" value="${child.age || ''}" placeholder="Age" min="0" max="120">
                                </div>
                                <div class="form-group">
                                    <select name="children[][gender]" class="input-field">
                                        <option value="">Select Gender</option>
                                        <option value="Male" ${child.gender === 'Male' ? 'selected' : ''}>Male</option>
                                        <option value="Female" ${child.gender === 'Female' ? 'selected' : ''}>Female</option>
                                        <option value="Other" ${child.gender === 'Other' ? 'selected' : ''}>Other</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <button type="button" class="btn-icon remove-field"><i class="ri-delete-bin-line"></i></button>
                                </div>
                            </div>
                        `;
                        childrenContainer.appendChild(fieldDiv);
                    });
                } else {
                    addDynamicField(childrenContainer, 'child');
                }

                // Handle dependents
                if (family.dependents && family.dependents.length > 0) {
                    family.dependents.forEach(dependent => {
                        const fieldDiv = document.createElement('div');
                        fieldDiv.className = 'form-group dynamic-field';
                        fieldDiv.innerHTML = `
                            <div class="grid">
                                <div class="form-group">
                                    <input type="text" name="dependents[][name]" class="input-field" value="${dependent.name || ''}" placeholder="Dependent Name">
                                </div>
                                <div class="form-group">
                                    <select name="dependents[][relationship]" class="input-field">
                                        <option value="">Select Relationship</option>
                                        <option value="Father" ${dependent.relationship === 'Father' ? 'selected' : ''}>Father</option>
                                        <option value="Mother" ${dependent.relationship === 'Mother' ? 'selected' : ''}>Mother</option>
                                        <option value="Spouse's Father" ${dependent.relationship === 'Spouse\'s Father' ? 'selected' : ''}>Spouse's Father</option>
                                        <option value="Spouse's Mother" ${dependent.relationship === 'Spouse\'s Mother' ? 'selected' : ''}>Spouse's Mother</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <input type="number" name="dependents[][age]" class="input-field" value="${dependent.age || ''}" placeholder="Age" min="0" max="120">
                                </div>
                                <div class="form-group">
                                    <button type="button" class="btn-icon remove-field"><i class="ri-delete-bin-line"></i></button>
                                </div>
                            </div>
                        `;
                        dependentsContainer.appendChild(fieldDiv);
                    });
                } else {
                    addDynamicField(dependentsContainer, 'dependent');
                }

                // Add event listeners for remove buttons
                document.querySelectorAll('.remove-field').forEach(button => {
                    button.addEventListener('click', () => {
                        const fieldDiv = button.closest('.dynamic-field');
                        if (fieldDiv.parentElement.children.length > 1) {
                            fieldDiv.remove();
                        }
                    });
                });

                showModal(editFamilyModal);
            } catch (e) {
                console.error('Failed to parse family data:', e);
                errorMessage.textContent = 'Error loading family data. Please try again.';
                showPopup(errorPopup);
            }
        });
    });

    function addDynamicField(container, type) {
        const fieldDiv = document.createElement('div');
        fieldDiv.className = 'form-group dynamic-field';
        
        if (type === 'child') {
            fieldDiv.innerHTML = `
                <div class="grid">
                    <div class="form-group">
                        <input type="text" name="children[][name]" class="input-field" placeholder="Child Name">
                    </div>
                    <div class="form-group">
                        <input type="number" name="children[][age]" class="input-field" placeholder="Age" min="0" max="120">
                    </div>
                    <div class="form-group">
                        <select name="children[][gender]" class="input-field">
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
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
                        <input type="text" name="dependents[][name]" class="input-field" placeholder="Dependent Name">
                    </div>
                    <div class="form-group">
                        <select name="dependents[][relationship]" class="input-field">
                            <option value="">Select Relationship</option>
                            <option value="Father">Father</option>
                            <option value="Mother">Mother</option>
                            <option value="Spouse's Father">Spouse's Father</option>
                            <option value="Spouse's Mother">Spouse's Mother</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <input type="number" name="dependents[][age]" class="input-field" placeholder="Age" min="0" max="120">
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
    }

    document.getElementById('add-child').addEventListener('click', () => {
        addDynamicField(document.getElementById('children-container'), 'child');
    });

    document.getElementById('add-dependent').addEventListener('click', () => {
        addDynamicField(document.getElementById('dependents-container'), 'dependent');
    });

    // Delete modal
    deleteButtons.forEach(button => {
        button.addEventListener('click', () => {
            const id = button.getAttribute('data-id');
            document.getElementById('delete-id').value = id;
            showModal(deleteModal);
        });
    });

    // Close modals
    closeButtons.forEach(button => {
        button.addEventListener('click', () => {
            const modal = button.closest('.modal');
            if (modal) {
                hideModal(modal);
                // Show cancel popup and start countdown
                showPopup(cancelPopup);
                startCountdown('cancel-countdown', window.location.href);
            }
        });
    });

    // Accordion toggle
    accordionHeaders.forEach(header => {
        header.addEventListener('click', () => {
            const targetId = header.getAttribute('data-target');
            const content = document.getElementById(targetId);
            const isActive = content.classList.contains('active');
            document.querySelectorAll('.accordion-content').forEach(c => {
                c.classList.remove('active');
            });
            if (!isActive) {
                content.classList.add('active');
            }
        });
    });

    // Edit form submission with validation
    editForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const contactNumber = document.getElementById('edit-contact_number').value;
        if (!/^\+94\d{9}$/.test(contactNumber)) {
            document.getElementById('edit-contact_number-error').classList.add('show');
            return;
        }
        const formData = new FormData(editForm);
        formData.append('update', 'true');

        try {
            const response = await fetch('', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const result = await response.json();
            if (result.success) {
                successMessage.textContent = result.message;
                showPopup(successPopup);
                startCountdown('success-countdown', window.location.href);
            } else {
                errorMessage.textContent = result.message;
                showPopup(errorPopup);
            }
        } catch (error) {
            console.error('Error submitting edit form:', error);
            errorMessage.textContent = 'An unexpected error occurred. Please try again.';
            showPopup(errorPopup);
        }
    });

    // Edit details form submission with validation
    editDetailsForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const contactNumber = document.getElementById('edit-details-contact_number').value;
        if (!/^\+94\d{9}$/.test(contactNumber)) {
            document.getElementById('edit-details-contact_number-error').classList.add('show');
            return;
        }
        const formData = new FormData(editDetailsForm);
        formData.append('update_details', 'true');

        try {
            const response = await fetch('', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const result = await response.json();
            if (result.success) {
                successMessage.textContent = result.message;
                showPopup(successPopup);
                startCountdown('success-countdown', window.location.href);
            } else {
                errorMessage.textContent = result.message;
                showPopup(errorPopup);
            }
        } catch (error) {
            console.error('Error submitting edit details form:', error);
            errorMessage.textContent = 'An unexpected error occurred. Please try again.';
            showPopup(errorPopup);
        }
    });

    // Edit Family Form Submission
    editFamilyForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        try {
            const formData = new FormData(this);
            
            // Add member_id to formData
            formData.append('member_id', editFamilyForm.dataset.memberId);
            
            // Add update_family flag
            formData.append('update_family', '1');
            
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });
            
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            
            const result = await response.json();
            
            if (result.success) {
                // Hide the edit family modal
                hideModal(editFamilyModal);
                
                // Show success message
                successMessage.textContent = result.message || 'Family details updated successfully';
                showPopup(successPopup);
                startCountdown('success-countdown', window.location.href);
            } else {
                throw new Error(result.message || 'Failed to update family details');
            }
        } catch (error) {
            console.error('Error:', error);
            errorMessage.textContent = error.message || 'An error occurred while updating family details';
            showPopup(errorPopup);
            startCountdown('error-countdown', window.location.href);
        }
    });

    // Delete form submission
    deleteForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(deleteForm);
        formData.append('delete', 'true');

        try {
            const response = await fetch('', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const result = await response.json();
            if (result.success) {
                successMessage.textContent = result.message;
                showPopup(successPopup);
                startCountdown('success-countdown', 'members.php');
            } else {
                errorMessage.textContent = result.message;
                showPopup(errorPopup);
            }
        } catch (error) {
            console.error('Error submitting delete form:', error);
            errorMessage.textContent = 'An unexpected error occurred. Please try again.';
            showPopup(errorPopup);
        }
    });
});
