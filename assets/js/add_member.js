document.addEventListener('DOMContentLoaded', () => {
    const addMemberForm = document.getElementById('add-member-form');
    const childrenContainer = document.getElementById('children-list');
    const dependentsContainer = document.getElementById('dependents-list');
    const addChildBtn = document.getElementById('add-child-btn');
    const addDependentBtn = document.getElementById('add-dependent-btn');
    const addSpouseBtn = document.getElementById('add-spouse-btn');
    const removeSpouseBtn = document.getElementById('remove-spouse-btn');
    const spouseDetails = document.getElementById('spouse-details');
    const childrenDetails = document.getElementById('children-details');
    const dependentsDetails = document.getElementById('dependents-details');
    const successPopup = document.getElementById('success-popup');
    const errorPopup = document.getElementById('error-popup');
    const cancelPopup = document.getElementById('cancel-popup');
    const successMessage = document.getElementById('success-message');
    const errorMessage = document.getElementById('error-message');
    const popupOverlay = document.getElementById('popup-overlay');
    const cancelButton = document.getElementById('cancel-button');

    let childCount = 0;
    let dependentCount = 0;

    // Spouse handling
    addSpouseBtn.addEventListener('click', () => {
        spouseDetails.style.display = 'block';
        addSpouseBtn.style.display = 'none';
    });

    removeSpouseBtn.addEventListener('click', () => {
        spouseDetails.style.display = 'none';
        addSpouseBtn.style.display = 'inline-flex';
        document.getElementById('spouse_name').value = '';
        document.getElementById('spouse_dob').value = '';
        document.getElementById('spouse_gender').value = '';
    });

    // Cancel button handler
    cancelButton.addEventListener('click', () => {
        showPopup(cancelPopup);
        startCountdown('cancel-countdown', 'members.php');
    });

    function showPopup(popup) {
        popupOverlay.classList.add('show');
        popup.classList.add('show');
        document.body.style.overflow = 'hidden';
        document.body.style.position = 'fixed';
        document.body.style.width = '100%';
    }

    function hidePopup(popup) {
        popupOverlay.classList.remove('show');
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
                        window.location.replace(redirectUrl);
                    }
                }
            }, 1000);
        }
    }

    function addDynamicField(container, type) {
        const fieldDiv = document.createElement('div');
        fieldDiv.className = 'entry-card';
        
        if (type === 'child') {
            fieldDiv.innerHTML = `
                <button type="button" class="remove-btn">
                    <i class="ri-close-circle-line" style="font-size: 1.2rem;"></i>
                </button>
                <div class="grid">
                    <div class="form-group">
                        <label class="form-label">Child Name</label>
                        <input type="text" name="children[${childCount}][name]" class="input-field">
                        <span class="error-text">Child name is required if provided.</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" name="children[${childCount}][dob]" class="input-field" max="${new Date().toISOString().split('T')[0]}">
                        <span class="error-text">Date of birth is required if provided.</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Gender</label>
                        <select name="children[${childCount}][gender]" class="input-field">
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                        <span class="error-text">Gender is required if provided.</span>
                    </div>
                </div>
            `;
        } else if (type === 'dependent') {
            fieldDiv.innerHTML = `
                <button type="button" class="remove-btn">
                    <i class="ri-close-circle-line" style="font-size: 1.2rem;"></i>
                </button>
                <div class="grid">
                    <div class="form-group">
                        <label class="form-label">Name</label>
                        <input type="text" name="dependents[${dependentCount}][name]" class="input-field">
                        <span class="error-text">Name is required if provided.</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Relationship</label>
                        <select name="dependents[${dependentCount}][relationship]" class="input-field">
                            <option value="">Select Relationship</option>
                            <option value="Father">Father</option>
                            <option value="Mother">Mother</option>
                            <option value="Spouse's Father">Spouse's Father</option>
                            <option value="Spouse's Mother">Spouse's Mother</option>
                        </select>
                        <span class="error-text">Relationship is required if provided.</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" name="dependents[${dependentCount}][dob]" class="input-field" max="${new Date().toISOString().split('T')[0]}">
                        <span class="error-text">Date of birth is required if provided.</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Address</label>
                        <input type="text" name="dependents[${dependentCount}][address]" class="input-field">
                        <span class="error-text">Address is required if provided.</span>
                    </div>
                </div>
            `;
        }
        
        container.appendChild(fieldDiv);

        fieldDiv.querySelector('.remove-btn').addEventListener('click', () => {
            if (type === 'child') {
                childCount--;
                if (childCount === 0) {
                    childrenDetails.style.display = 'none';
                    addChildBtn.style.display = 'inline-flex';
                }
            } else if (type === 'dependent') {
                dependentCount--;
                if (dependentCount === 0) {
                    dependentsDetails.style.display = 'none';
                    addDependentBtn.style.display = 'inline-flex';
                }
            }
            fieldDiv.remove();
        });
    }

    // Add child button click handler
    addChildBtn.addEventListener('click', () => {
        if (childCount < 5) {
            childrenDetails.style.display = 'block';
            addChildBtn.style.display = 'none';
            addDynamicField(childrenContainer, 'child');
            childCount++;
        } else {
            errorMessage.textContent = 'Maximum of 5 children are allowed.';
            showPopup(errorPopup);
            startCountdown('error-countdown', null);
        }
    });

    // Add another child button handler
    document.getElementById('add-another-child-btn').addEventListener('click', () => {
        if (childCount < 5) {
            addDynamicField(childrenContainer, 'child');
            childCount++;
        } else {
            errorMessage.textContent = 'Maximum of 5 children are allowed.';
            showPopup(errorPopup);
            startCountdown('error-countdown', null);
        }
    });

    // Add dependent button click handler
    addDependentBtn.addEventListener('click', () => {
        if (dependentCount < 4) {
            dependentsDetails.style.display = 'block';
            addDependentBtn.style.display = 'none';
            addDynamicField(dependentsContainer, 'dependent');
            dependentCount++;
        } else {
            errorMessage.textContent = 'Maximum of 4 dependents are allowed.';
            showPopup(errorPopup);
            startCountdown('error-countdown', null);
        }
    });

    // Add another dependent button handler
    document.getElementById('add-another-dependent-btn').addEventListener('click', () => {
        if (dependentCount < 4) {
            addDynamicField(dependentsContainer, 'dependent');
            dependentCount++;
        } else {
            errorMessage.textContent = 'Maximum of 4 dependents are allowed.';
            showPopup(errorPopup);
            startCountdown('error-countdown', null);
        }
    });

    // Form submission handler
    addMemberForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        try {
            const formData = new FormData(addMemberForm);
            
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                throw new Error('Server returned non-JSON response');
            }
            
            const result = await response.json();
            
            if (result.success) {
                successMessage.textContent = result.message;
                showPopup(successPopup);
                startCountdown('success-countdown', 'members.php');
            } else {
                throw new Error(result.message || 'Failed to add member');
            }
        } catch (error) {
            console.error('Error:', error);
            errorMessage.textContent = error.message || 'An error occurred while adding the member';
            showPopup(errorPopup);
            startCountdown('error-countdown', null);
        }
    });
});