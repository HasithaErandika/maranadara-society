document.addEventListener('DOMContentLoaded', () => {
    // Show PHP-driven popups
    if (window.successMsg && window.successMsg !== '""') {
        showPopup('success', window.successMsg.replace(/"/g, ''));
    }
    if (window.errorMsg && window.errorMsg !== '""') {
        showPopup('error', window.errorMsg.replace(/"/g, ''));
    }

    // Tab Switching
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const tab = btn.dataset.tab;
            document.querySelectorAll('.tab-content').forEach(content => content.classList.add('hidden'));
            document.getElementById(`tab-${tab}`).classList.remove('hidden');
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            window.history.replaceState({}, '', `?tab=${tab}`);
        });
    });

    // Form Validation
    function validateForm(formId) {
        const form = document.getElementById(formId);
        let isValid = true;

        // Validate required fields
        form.querySelectorAll('[required]').forEach(field => {
            const error = document.getElementById(`${field.id}-error`);
            if (!field.value || field.value === '') {
                error.textContent = `Please select a ${field.name.replace('_', ' ')}.`;
                error.classList.remove('hidden');
                isValid = false;
            } else if (field.type === 'number' && field.value <= 0) {
                error.textContent = `${field.name.replace('_', ' ')} must be greater than 0.`;
                error.classList.remove('hidden');
                isValid = false;
            } else {
                error.classList.add('hidden');
            }
        });

        // Validate date format
        const dateField = form.querySelector('[name="date"]');
        const dateError = document.getElementById(`${dateField.id}-error`);
        if (dateField.value && !/^\d{4}-\d{2}-\d{2}$/.test(dateField.value)) {
            dateError.textContent = 'Please enter a valid date (YYYY-MM-DD).';
            dateError.classList.remove('hidden');
            isValid = false;
        } else {
            dateError.classList.add('hidden');
        }

        // Validate Loan Settlement
        const paymentType = form.querySelector('[name="payment_type"]').value;
        if (paymentType === 'Loan Settlement') {
            const loanId = form.querySelector('[name="loan_id"]').value;
            const loanError = document.getElementById(formId === 'add-payment-form' ? 'loan_id-error' : 'edit-loan_id-error');
            if (!loanId || loanId <= 0 || loanId === '') {
                loanError.textContent = 'Please select a valid loan.';
                loanError.classList.remove('hidden');
                isValid = false;
            } else {
                loanError.classList.add('hidden');
            }
        }

        return isValid;
    }

    // Form Reset
    window.resetForm = function(formId) {
        const form = document.getElementById(formId);
        form.reset();
        form.querySelectorAll('.error-text').forEach(e => e.classList.add('hidden'));
        toggleLoanSection(formId);
    };

    // Toggle Loan Section
    function toggleLoanSection(formId) {
        const form = document.getElementById(formId);
        const type = form.querySelector('[name="payment_type"]').value;
        const section = form.querySelector(formId === 'add-payment-form' ? '#loan-section' : '#edit-loan-section');
        const select = section.querySelector('select');
        section.classList.toggle('hidden', type !== 'Loan Settlement');
        select.required = type === 'Loan Settlement';
        if (type === 'Loan Settlement') {
            updateLoans(formId);
        } else {
            select.innerHTML = '<option value="" disabled selected>Select a Loan</option>';
            form.querySelector(`#${formId === 'add-payment-form' ? 'loan_id' : 'edit-loan_id'}-error`).classList.add('hidden');
        }
    }

    // Fetch Loans
    async function updateLoans(formId, selectedId = null) {
        const form = document.getElementById(formId);
        const memberId = form.querySelector('[name="member_id"]').value;
        const dropdown = form.querySelector(`#${formId === 'add-payment-form' ? 'loan_id' : 'edit-loan_id'}`);
        const paymentType = form.querySelector('[name="payment_type"]').value;

        // Only fetch loans if payment type is Loan Settlement and member is selected
        if (paymentType !== 'Loan Settlement') {
            dropdown.innerHTML = '<option value="" disabled selected>Select a Loan</option>';
            return;
        }

        dropdown.innerHTML = '<option value="" disabled selected>Loading...</option>';

        if (!memberId || isNaN(memberId) || memberId <= 0) {
            dropdown.innerHTML = '<option value="" disabled selected>No member selected</option>';
            return;
        }

        try {
            const url = `/payments.php?action=get_loans&member_id=${encodeURIComponent(memberId)}`;
            console.log(`Fetching loans from: ${url}`);
            const response = await fetch(url, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (!response.ok) {
                throw new Error(`HTTP error ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();
            console.log('Loan fetch response:', data);

            dropdown.innerHTML = '<option value="" disabled selected>Select a Loan</option>';

            if (data.status !== 'success') {
                throw new Error(data.message || 'Invalid response from server.');
            }

            if (!data.data || data.data.length === 0) {
                dropdown.innerHTML += '<option value="" disabled>There are no loans to settle</option>';
            } else {
                data.data.forEach(loan => {
                    const selected = loan.id == selectedId ? ' selected' : '';
                    dropdown.innerHTML += `<option value="${loan.id}"${selected}>Loan #${loan.id} - LKR ${Number(loan.amount).toFixed(2)} (Monthly: ${Number(loan.monthly_payment).toFixed(2)})</option>`;
                });
            }
        } catch (error) {
            console.error('Loan fetch error:', error.message);
            dropdown.innerHTML = '<option value="" disabled>Error loading loans</option>';
            showPopup('error', error.message.includes('HTTP') ? `Cannot reach loan service: ${error.message}` : 'Failed to load loans.');
        }
    }

    // Add Payment Form
    document.getElementById('add-payment-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!validateForm('add-payment-form')) return;

        const form = e.target;
        const button = form.querySelector('button[name="add"]');
        button.disabled = true;

        try {
            const formData = new FormData(form);
            formData.append('add', 'true');
            console.log('Submitting form data:', Object.fromEntries(formData));

            const response = await fetch('', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (!response.ok) {
                throw new Error(`HTTP error ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();
            console.log('Server response:', data);

            if (data.success) {
                showPopup('success', data.message);
                setTimeout(() => window.location.reload(), 3000);
            } else {
                showPopup('error', data.message || 'Failed to add payment.');
            }
        } catch (error) {
            console.error('Add payment error:', error.message);
            showPopup('error', error.message.includes('HTTP') ? 'Server error: ' + error.message : 'Failed to add payment: ' + error.message);
        } finally {
            button.disabled = false;
        }
    });

    // Edit Payment Form
    document.getElementById('edit-payment-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!validateForm('edit-payment-form')) return;

        const form = e.target;
        const button = form.querySelector('button[name="update"]');
        button.disabled = true;

        try {
            const formData = new FormData(form);
            formData.append('update', 'true');
            console.log('Submitting edit form data:', Object.fromEntries(formData));

            const response = await fetch('', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (!response.ok) {
                throw new Error(`HTTP error ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();
            console.log('Server response:', data);

            if (data.success) {
                showPopup('success', data.message);
                setTimeout(() => window.location.reload(), 3000);
            } else {
                showPopup('error', data.message || 'Failed to update payment.');
            }
        } catch (error) {
            console.error('Edit payment error:', error.message);
            showPopup('error', error.message.includes('HTTP') ? 'Server error: ' + error.message : 'Failed to update payment: ' + error.message);
        } finally {
            button.disabled = false;
        }
    });

    // Show Popup
    function showPopup(type, message = '') {
        const overlay = document.getElementById('popup-overlay');
        const popup = document.getElementById(`${type}-popup`);
        const messageElement = document.getElementById(`${type}-message`);
        const countdownElement = document.getElementById(`${type}-countdown`);

        if (messageElement && message) {
            messageElement.textContent = message;
        }
        overlay.classList.add('show');
        popup.classList.add('show');

        let countdown = 3;
        countdownElement.textContent = countdown;
        const interval = setInterval(() => {
            countdown--;
            countdownElement.textContent = countdown;
            if (countdown <= 0) {
                clearInterval(interval);
                overlay.classList.remove('show');
                popup.classList.remove('show');
                if (type === 'cancel') {
                    closeEditModal();
                }
            }
        }, 1000);
    }

    window.showCancelPopup = function() {
        showPopup('cancel', 'The operation has been cancelled.');
    };

    // Edit Modal
    window.showEditModal = function(payment) {
        const modal = document.getElementById('edit-modal');
        document.getElementById('edit-id').value = payment.id;
        document.getElementById('edit-member_id').value = payment.member_id;
        document.getElementById('edit-amount').value = payment.amount;
        document.getElementById('edit-date').value = payment.date;
        document.getElementById('edit-payment_mode').value = payment.payment_mode;
        document.getElementById('edit-payment_type').value = payment.payment_type;
        document.getElementById('edit-receipt_number').value = payment.receipt_number || '';
        document.getElementById('edit-remarks').value = payment.remarks || '';
        const loanSection = document.getElementById('edit-loan-section');
        const select = loanSection.querySelector('select');
        loanSection.classList.toggle('hidden', payment.payment_type !== 'Loan Settlement');
        select.required = payment.payment_type === 'Loan Settlement';
        if (payment.payment_type === 'Loan Settlement') {
            updateLoans('edit-payment-form', payment.loan_id);
        }
        modal.style.display = 'flex';
        document.getElementById('edit-member_id').focus();
    };

    function closeEditModal() {
        document.getElementById('edit-modal').style.display = 'none';
        document.getElementById('edit-payment-form').querySelectorAll('.error-text').forEach(e => e.classList.add('hidden'));
    }

    // Confirm Payment
    window.confirmPayment = async function(id) {
        if (!confirm('Confirm this payment?')) return;

        try {
            const formData = new FormData();
            formData.append('id', id);
            formData.append('confirm', 'true');
            console.log('Confirming payment:', id);

            const response = await fetch('', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (!response.ok) {
                throw new Error(`HTTP error ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();
            console.log('Server response:', data);

            if (data.success) {
                showPopup('success', data.message);
                setTimeout(() => window.location.reload(), 3000);
            } else {
                showPopup('error', data.message || 'Failed to confirm payment.');
            }
        } catch (error) {
            console.error('Confirm payment error:', error.message);
            showPopup('error', error.message.includes('HTTP') ? 'Server error: ' + error.message : 'Failed to confirm payment: ' + error.message);
        }
    };

    // Delete Payment
    window.deletePayment = async function(id) {
        if (!confirm('Delete this payment?')) return;

        try {
            const formData = new FormData();
            formData.append('id', id);
            formData.append('delete', 'true');
            console.log('Deleting payment:', id);

            const response = await fetch('', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (!response.ok) {
                throw new Error(`HTTP error ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();
            console.log('Server response:', data);

            if (data.success) {
                showPopup('success', data.message);
                setTimeout(() => window.location.reload(), 3000);
            } else {
                showPopup('error', data.message || 'Failed to delete payment.');
            }
        } catch (error) {
            console.error('Delete payment error:', error.message);
            showPopup('error', error.message.includes('HTTP') ? 'Server error: ' + error.message : 'Failed to delete payment: ' + error.message);
        }
    };

    // Table Pagination and Search
    function setupTable(tableId, paginationId, rowsPerPage = 10) {
        const table = document.getElementById(tableId);
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const pagination = document.getElementById(paginationId);
        let currentPage = 1;
        let filteredRows = rows;

        function renderPage(page) {
            const start = (page - 1) * rowsPerPage;
            const end = start + rowsPerPage;
            tbody.innerHTML = '';
            filteredRows.slice(start, end).forEach(row => tbody.appendChild(row));

            const totalPages = Math.ceil(filteredRows.length / rowsPerPage);
            pagination.innerHTML = '';
            for (let i = 1; i <= totalPages; i++) {
                const btn = document.createElement('button');
                btn.className = `pagination-btn ${i === page ? 'active' : ''}`;
                btn.textContent = i;
                btn.disabled = i === page;
                btn.addEventListener('click', () => {
                    currentPage = i;
                    renderPage(i);
                });
                pagination.appendChild(btn);
            }
        }

        const searchInput = document.getElementById(tableId.replace('-table', '-search'));
        searchInput.addEventListener('input', () => {
            const query = searchInput.value.toLowerCase();
            filteredRows = rows.filter(row => {
                return Array.from(row.cells).some(cell => cell.textContent.toLowerCase().includes(query));
            });
            currentPage = 1;
            renderPage(1);
        });

        renderPage(1);
    }

    // Initialize Tables
    setupTable('society-table', 'society-pagination');
    setupTable('membership-table', 'membership-pagination');
    setupTable('loan-table', 'loan-pagination');

    // Event Listeners
    document.getElementById('payment_type').addEventListener('change', () => {
        toggleLoanSection('add-payment-form');
        updateLoans('add-payment-form');
    });
    document.getElementById('edit-payment_type').addEventListener('change', () => {
        toggleLoanSection('edit-payment-form');
        updateLoans('edit-payment-form');
    });
    document.getElementById('member_id').addEventListener('change', () => {
        if (document.getElementById('payment_type').value === 'Loan Settlement') {
            updateLoans('add-payment-form');
        }
    });
    document.getElementById('edit-member_id').addEventListener('change', () => {
        if (document.getElementById('edit-payment_type').value === 'Loan Settlement') {
            updateLoans('edit-payment-form');
        }
    });
});