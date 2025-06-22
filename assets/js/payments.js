document.addEventListener('DOMContentLoaded', () => {
    // Show success or error popup based on PHP variables
    if (window.successMsg) {
        showSuccessPopup(window.successMsg);
    } else if (window.errorMsg) {
        showErrorPopup(window.errorMsg);
    }

    // Form submission handling for Add Payment
    const addPaymentForm = document.getElementById('add-payment-form');
    if (addPaymentForm) {
        addPaymentForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(addPaymentForm);

            try {
                const response = await fetch(window.baseUrl, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const result = await response.json();
                if (result.success) {
                    showSuccessPopup(result.message);
                    addPaymentForm.reset();
                } else {
                    showErrorPopup(result.message);
                }
            } catch (error) {
                showErrorPopup('An unexpected error occurred. Please try again.');
            }
        });
    }

    // Tab switching
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');

    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            const tab = button.getAttribute('data-tab');
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => content.classList.add('hidden'));
            button.classList.add('active');
            document.getElementById(`tab-${tab}`).classList.remove('hidden');

            // Update URL without reloading
            const url = new URL(window.location);
            url.searchParams.set('tab', tab);
            history.pushState({}, '', url);
        });
    });

    // Handle loan section visibility
    const paymentTypeSelect = document.getElementById('payment_type');
    const loanSection = document.getElementById('loan-section');
    const loanSelect = document.getElementById('loan_id');

    // Hide loan section on page load unless 'Loan Settlement' is selected
    if (paymentTypeSelect && loanSection) {
        if (paymentTypeSelect.value !== 'Loan Settlement') {
            loanSection.classList.add('hidden');
        }
    }

    if (paymentTypeSelect && loanSection && loanSelect) {
        paymentTypeSelect.addEventListener('change', async () => {
            if (paymentTypeSelect.value === 'Loan Settlement') {
                loanSection.classList.remove('hidden');
                const memberId = document.getElementById('member_id').value;
                if (memberId) {
                    try {
                        const response = await fetch(`${window.baseUrl}?action=get_loans&member_id=${memberId}`, {
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        });
                        const result = await response.json();
                        if (result.status === 'success') {
                            loanSelect.innerHTML = '<option value="" disabled selected>Select a Loan</option>';
                            result.data.forEach(loan => {
                                loanSelect.innerHTML += `<option value="${loan.id}">Loan ID: ${loan.id} - ${loan.amount} LKR</option>`;
                            });
                        } else {
                            showErrorPopup(result.message);
                        }
                    } catch (error) {
                        showErrorPopup('Failed to fetch loans.');
                    }
                }
            } else {
                loanSection.classList.add('hidden');
                loanSelect.innerHTML = '<option value="" disabled selected>Select a Loan</option>';
            }
        });
    }

    // Edit payment handling
    const editButtons = document.querySelectorAll('.edit-payment');
    const editModal = document.getElementById('edit-modal');
    const editForm = document.getElementById('edit-payment-form');
    const editLoanSection = document.getElementById('edit-loan-section');
    const editLoanSelect = document.getElementById('edit-loan_id');

    editButtons.forEach(button => {
        button.addEventListener('click', async () => {
            const payment = JSON.parse(button.getAttribute('data-payment'));
            document.getElementById('edit-id').value = payment.id;
            document.getElementById('edit-member_id').value = payment.member_id;
            document.getElementById('edit-amount').value = payment.amount;
            document.getElementById('edit-date').value = payment.date;
            document.getElementById('edit-payment_mode').value = payment.payment_mode;
            document.getElementById('edit-payment_type').value = payment.payment_type;
            document.getElementById('edit-receipt_number').value = payment.receipt_number || '';
            document.getElementById('edit-remarks').value = payment.remarks || '';

            if (payment.payment_type === 'Loan Settlement') {
                editLoanSection.classList.remove('hidden');
                try {
                    const response = await fetch(`${window.baseUrl}?action=get_loans&member_id=${payment.member_id}`, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const result = await response.json();
                    if (result.status === 'success') {
                        editLoanSelect.innerHTML = '<option value="" disabled>Select a Loan</option>';
                        result.data.forEach(loan => {
                            const selected = loan.id == payment.loan_id ? 'selected' : '';
                            editLoanSelect.innerHTML += `<option value="${loan.id}" ${selected}>Loan ID: ${loan.id} - ${loan.amount} LKR</option>`;
                        });
                    }
                } catch (error) {
                    showErrorPopup('Failed to fetch loans.');
                }
            } else {
                editLoanSection.classList.add('hidden');
            }

            editModal.style.display = 'flex';
        });
    });

    // Confirm, delete, and edit form submission
    document.querySelectorAll('.confirm-payment, .delete-payment').forEach(button => {
        button.addEventListener('click', async () => {
            const id = button.getAttribute('data-id');
            const action = button.classList.contains('confirm-payment') ? 'confirm' : 'delete';
            try {
                const response = await fetch(window.baseUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: `id=${id}&${action}=1`
                });
                const result = await response.json();
                if (result.success) {
                    showSuccessPopup(result.message);
                    setTimeout(() => location.reload(), 3000);
                } else {
                    showErrorPopup(result.message);
                }
            } catch (error) {
                showErrorPopup('An unexpected error occurred.');
            }
        });
    });

    if (editForm) {
        editForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(editForm);
            formData.append('update', '1');

            try {
                const response = await fetch(window.baseUrl, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const result = await response.json();
                if (result.success) {
                    showSuccessPopup(result.message);
                    editModal.style.display = 'none';
                    setTimeout(() => location.reload(), 3000);
                } else {
                    showErrorPopup(result.message);
                }
            } catch (error) {
                showErrorPopup('An unexpected error occurred.');
            }
        });
    }

    // Popup functions
    function showSuccessPopup(message) {
        const popup = document.getElementById('success-popup');
        const overlay = document.getElementById('popup-overlay');
        const messageEl = document.getElementById('success-message');
        messageEl.textContent = message;
        popup.classList.add('show');
        overlay.classList.add('show');

        let countdown = 3;
        const countdownEl = document.getElementById('success-countdown');
        countdownEl.textContent = countdown;
        const interval = setInterval(() => {
            countdown--;
            countdownEl.textContent = countdown;
            if (countdown <= 0) {
                clearInterval(interval);
                popup.classList.remove('show');
                overlay.classList.remove('show');
                location.reload();
            }
        }, 1000);
    }

    function showErrorPopup(message) {
        const popup = document.getElementById('error-popup');
        const overlay = document.getElementById('popup-overlay');
        const messageEl = document.getElementById('error-message');
        messageEl.textContent = message;
        popup.classList.add('show');
        overlay.classList.add('show');

        let countdown = 3;
        const countdownEl = document.getElementById('error-countdown');
        countdownEl.textContent = countdown;
        const interval = setInterval(() => {
            countdown--;
            countdownEl.textContent = countdown;
            if (countdown <= 0) {
                clearInterval(interval);
                popup.classList.remove('show');
                overlay.classList.remove('show');
            }
        }, 1000);
    }

    function showCancelPopup() {
        const popup = document.getElementById('cancel-popup');
        const overlay = document.getElementById('popup-overlay');
        popup.classList.add('show');
        overlay.classList.add('show');

        let countdown = 3;
        const countdownEl = document.getElementById('cancel-countdown');
        countdownEl.textContent = countdown;
        const interval = setInterval(() => {
            countdown--;
            countdownEl.textContent = countdown;
            if (countdown <= 0) {
                clearInterval(interval);
                popup.classList.remove('show');
                overlay.classList.remove('show');
                document.getElementById('edit-modal').style.display = 'none';
                document.getElementById('add-payment-form').reset();
            }
        }, 1000);
    }

    // Reset form
    window.resetForm = (formId) => {
        document.getElementById(formId).reset();
        showCancelPopup();
    };

    // Search functionality
    const searchInputs = {
        'society': document.getElementById('society-search'),
        'membership': document.getElementById('membership-search'),
        'loan': document.getElementById('loan-search')
    };

    Object.keys(searchInputs).forEach(tab => {
        const input = searchInputs[tab];
        if (input) {
            input.addEventListener('input', () => {
                const searchTerm = input.value.toLowerCase();
                const rows = document.querySelectorAll(`#${tab}-table tbody tr`);
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(searchTerm) ? '' : 'none';
                });
            });
        }
    });

    // Pagination (simplified)
    const tables = ['society', 'membership', 'loan'];
    tables.forEach(tab => {
        const table = document.getElementById(`${tab}-table`);
        const pagination = document.getElementById(`${tab}-pagination`);
        if (table && pagination) {
            const rows = table.querySelectorAll('tbody tr');
            const rowsPerPage = 10;
            let currentPage = 1;

            function showPage(page) {
                rows.forEach((row, index) => {
                    row.style.display = (index >= (page - 1) * rowsPerPage && index < page * rowsPerPage) ? '' : 'none';
                });

                pagination.innerHTML = '';
                const totalPages = Math.ceil(rows.length / rowsPerPage);
                for (let i = 1; i <= totalPages; i++) {
                    const btn = document.createElement('button');
                    btn.className = `pagination-btn ${i === page ? 'active' : ''}`;
                    btn.textContent = i;
                    btn.addEventListener('click', () => {
                        currentPage = i;
                        showPage(i);
                    });
                    pagination.appendChild(btn);
                }
            }

            showPage(currentPage);
        }
    });

    // Auto-Add Membership Fees with custom confirmation popup
    const autoAddFeesBtn = document.getElementById('auto-add-fees-btn');
    const confirmAutoAddPopup = document.getElementById('confirm-auto-add-fees-popup');
    const confirmAutoAddYes = document.getElementById('confirm-auto-add-fees-yes');
    const confirmAutoAddNo = document.getElementById('confirm-auto-add-fees-no');
    const popupOverlay = document.getElementById('popup-overlay');

    if (autoAddFeesBtn && confirmAutoAddPopup && confirmAutoAddYes && confirmAutoAddNo && popupOverlay) {
        autoAddFeesBtn.addEventListener('click', () => {
            confirmAutoAddPopup.classList.add('show');
            popupOverlay.classList.add('show');
        });
        confirmAutoAddNo.addEventListener('click', () => {
            confirmAutoAddPopup.classList.remove('show');
            popupOverlay.classList.remove('show');
        });
        confirmAutoAddYes.addEventListener('click', () => {
            confirmAutoAddPopup.classList.remove('show');
            // Keep overlay for success popup
            fetch(window.baseUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: 'auto_add_fees=1&tab=membership'
            })
            .then(res => res.json())
            .then(result => {
                if (result.success) {
                    showSuccessPopup(result.message);
                } else {
                    showErrorPopup(result.message || 'Error adding membership fees.');
                }
            })
            .catch(() => showErrorPopup('An unexpected error occurred.'));
        });
    }

    // Auto-Add Loan Settlements with custom confirmation popup
    const autoAddLoanBtn = document.getElementById('auto-add-loan-btn');
    const confirmAutoAddLoanPopup = document.getElementById('confirm-auto-add-loan-popup');
    const confirmAutoAddLoanYes = document.getElementById('confirm-auto-add-loan-yes');
    const confirmAutoAddLoanNo = document.getElementById('confirm-auto-add-loan-no');

    if (autoAddLoanBtn && confirmAutoAddLoanPopup && confirmAutoAddLoanYes && confirmAutoAddLoanNo && popupOverlay) {
        autoAddLoanBtn.addEventListener('click', () => {
            confirmAutoAddLoanPopup.classList.add('show');
            popupOverlay.classList.add('show');
        });
        confirmAutoAddLoanNo.addEventListener('click', () => {
            confirmAutoAddLoanPopup.classList.remove('show');
            popupOverlay.classList.remove('show');
        });
        confirmAutoAddLoanYes.addEventListener('click', () => {
            confirmAutoAddLoanPopup.classList.remove('show');
            popupOverlay.classList.remove('show');
            fetch(window.baseUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: 'auto_add_loan_settlements=1&tab=loan'
            })
            .then(res => res.json())
            .then(result => {
                if (result.success) {
                    showSuccessPopup(result.message || 'Loan settlements added successfully!');
                } else {
                    showErrorPopup(result.message || 'Failed to add loan settlements.');
                }
            })
            .catch(() => {
                showErrorPopup('Failed to add loan settlements.');
            });
        });
    }
});