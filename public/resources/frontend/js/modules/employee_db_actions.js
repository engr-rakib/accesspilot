document.addEventListener('DOMContentLoaded', function() {
    const apiUrl = '../EmployeeDB/emp_get_post_update_api.php';
    const tableBody = document.querySelector('#employeeTable tbody');
    const searchInput = document.getElementById('employeeSearchInput');
    const searchButton = document.getElementById('employeeSearchButton');
    const permissions = window.employeeDbPermissions || {
        can_search: false,
        can_add: false,
        can_edit: false,
        can_delete: false,
        can_save: false
    };

    const fetchEmployees = async (search = '') => {
        if (!tableBody) return;

        try {
            const response = await fetch(`${apiUrl}?search=${encodeURIComponent(search)}`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const employees = await response.json();
            
            tableBody.innerHTML = ''; // Clear existing rows
            if (employees.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="9" class="text-center">No employees found.</td></tr>';
                return;
            }

            employees.forEach(emp => {
                const row = document.createElement('tr');
                const statusClass = emp.emp_sts ? `status-${emp.emp_sts.toLowerCase()}` : '';
                row.dataset.employee = JSON.stringify(emp); // Store full data on the row
                const actionButtons = [];

                if (permissions.can_edit) {
                    actionButtons.push(`
                        <button class="btn btn-icon btn-primary btn-sm btn-edit" data-noc-tip="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                    `);
                }

                if (permissions.can_delete) {
                    actionButtons.push(`
                        <button class="btn btn-icon btn-danger btn-sm btn-delete" data-noc-tip="Delete">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    `);
                }

                row.innerHTML = `
                    <td>${emp.emp_id}</td>
                    <td>${emp.emp_code || ''}</td>
                    <td>${emp.emp_name}</td>
                    <td>${emp.email || ''}</td>
                    <td>${emp.mobile || ''}</td>
                    <td>${emp.department_title || ''}</td>
                    <td>${emp.designation || ''}</td>
                    <td><span class="${statusClass}">${emp.emp_sts || ''}</span></td>
                    <td class="text-right">
                        ${actionButtons.join('')}
                    </td>
                `;
                tableBody.appendChild(row);
            });

        } catch (error) {
            console.error('Error fetching employees:', error);
            tableBody.innerHTML = `<tr><td colspan="9" class="text-center text-danger">Error loading data. ${error.message}</td></tr>`;
        }
    };

    if (searchButton && permissions.can_search) {
        searchButton.addEventListener('click', () => fetchEmployees(searchInput.value));
    }
    searchInput?.addEventListener('keyup', (e) => {
        if (e.key === 'Enter') {
            if (!permissions.can_search) {
                return;
            }
            fetchEmployees(searchInput.value);
        }
    });

    const addEmployeeBtn = document.getElementById('addEmployeeBtn');
    const modalElement = document.getElementById('employeeModal');
    const modal = new bootstrap.Modal(modalElement);
    const modalTitle = document.getElementById('modalTitle');
    const modalBody = modalElement.querySelector('.modal-body');
    const employeeForm = document.getElementById('employeeForm');
    let isEditMode = false;
    let editingEmpId = null;

    // Re-using the comprehensive field list from the old script
    const formFields = [
        { name: 'emp_id', label: 'Employee ID', required: true, readonly: true },
        { name: 'emp_code', label: 'Employee Code', required: true, readonly: true },
        { name: 'emp_name', label: 'Employee Name', required: true },
        { name: 'email', label: 'Email', type: 'email' },
        { name: 'mobile', label: 'Mobile' },
        { name: 'designation', label: 'Designation' },
        { name: 'department_title', label: 'Department' },
        { name: 'team_title', label: 'Team' },
        { name: 'emp_sts', label: 'Status', type: 'select', options: ['Active', 'Pending', 'Fired'] },
        { name: 'role_title', label: 'Role Title' },
        { name: 'operating_unit_title', label: 'Operating Unit' },
        { name: 'location_title', label: 'Location' },
        { name: 'joining_date', label: 'Joining Date' },
        { name: 'dob', label: 'Date of Birth' },
        { name: 'gender', label: 'Gender' },
        { name: 'address_permanent', label: 'Address', fullWidth: true },
    ];

    const generateFormHTML = (fields, data = {}) => {
        return fields.map(field => {
            const value = data[field.name] || '';
            const readonly = field.readonly ? 'readonly' : '';
            const required = field.required ? 'required' : '';
            const colClass = field.fullWidth ? 'col-12' : 'col-md-6';

            let inputHtml = '';
            if (field.type === 'select') {
                const optionsHtml = field.options.map(opt => `<option value="${opt}" ${opt === value ? 'selected' : ''}>${opt}</option>`).join('');
                inputHtml = `<select id="form_${field.name}" name="${field.name}" class="form-control" ${required}>${optionsHtml}</select>`;
            } else {
                inputHtml = `<input type="${field.type || 'text'}" id="form_${field.name}" name="${field.name}" class="form-control" value="${value}" ${readonly} ${required}>`;
            }

            return `<div class="${colClass} mb-3"><label for="form_${field.name}" class="form-label">${field.label}</label>${inputHtml}</div>`;
        }).join('');
    };

    const openModalForEdit = (employee) => {
        isEditMode = true;
        editingEmpId = employee.emp_id;
        modalTitle.textContent = `Edit Employee - ${employee.emp_name}`;
        modalBody.innerHTML = `<div class="row">${generateFormHTML(formFields, employee)}</div>`;
        modal.show();
    };

    const openModalForAdd = async () => {
        isEditMode = false;
        editingEmpId = null;
        modalTitle.textContent = 'Add New Employee';
        modalBody.innerHTML = '<p class="text-center">Loading form...</p>';
        modal.show();

        try {
            const response = await fetch(`${apiUrl}?action=getNextEmpId`);
            const data = await response.json();
            const nextId = data.next_emp_id;
            const initialData = {
                emp_id: nextId,
                emp_code: `GEN${nextId}`
            };
            modalBody.innerHTML = `<div class="row">${generateFormHTML(formFields, initialData)}</div>`;
        } catch (error) {
            console.error('Error getting next employee ID:', error);
            modalBody.innerHTML = '<p class="text-center text-danger">Could not load form. Please try again.</p>';
        }
    };

    if (addEmployeeBtn && permissions.can_add) {
        addEmployeeBtn.addEventListener('click', openModalForAdd);
    }

    // Event delegation for action buttons
    tableBody?.addEventListener('click', (e) => {
        const editButton = e.target.closest('.btn-edit');
        if (editButton && permissions.can_edit) {
            const employeeRow = editButton.closest('tr');
            const employee = JSON.parse(employeeRow.dataset.employee);
            openModalForEdit(employee);
        }

        const deleteButton = e.target.closest('.btn-delete');
        if (deleteButton && permissions.can_delete) {
            const employeeRow = deleteButton.closest('tr');
            const employee = JSON.parse(employeeRow.dataset.employee);
            if (confirm(`Are you sure you want to delete ${employee.emp_name} (ID: ${employee.emp_id})?`)) {
                deleteEmployee(employee.emp_id, employeeRow);
            }
        }
    });

    const deleteEmployee = async (empId, rowElement) => {
        try {
            const response = await fetch(apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ emp_id: empId, _method: 'delete' })
            });
            const result = await response.json();
            if (result.status === 'success') {
                rowElement.remove();
                // Optionally, show a success toast/notification here
            } else {
                throw new Error(result.message || 'Failed to delete employee.');
            }
        } catch (error) {
            console.error('Error deleting employee:', error);
            alert('Error: ' + error.message);
        }
    };

    employeeForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        if (!permissions.can_save) {
            alert('You do not have permission to save employee records.');
            return;
        }

        const formData = new FormData(employeeForm);
        const employeeData = Object.fromEntries(formData.entries());
        const submitButton = e.submitter || employeeForm.querySelector('[type="submit"]');

        submitButton.disabled = true;
        submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';

        try {
            const payload = {
                ...employeeData,
                _method: isEditMode ? 'update' : 'create'
            };
            if (isEditMode) {
                payload.emp_id = editingEmpId;
            }

            const response = await fetch(apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            if (!response.ok) {
                const errorResult = await response.json();
                throw new Error(errorResult.message || 'An unknown error occurred.');
            }

            modal.hide();
            fetchEmployees(); // Refresh the table

        } catch (error) {
            console.error('Error saving employee:', error);
            alert(`Error: ${error.message}`);
        } finally {
            submitButton.disabled = false;
            submitButton.innerHTML = 'Save';
        }
    });

    // Initial load
    fetchEmployees();
});
