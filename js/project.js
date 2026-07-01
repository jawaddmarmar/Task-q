// 1. Get Elements
const modal = document.getElementById("modal-box");
const overlay = document.getElementById("modal-overlay");
const addProjectBtn = document.getElementById("add_project_btn");
const cancelBtn = document.getElementById("btn-cancel");
const closeX = document.getElementById("close-x");
const createBtn = document.getElementById("btn-create");

const escapeHTML = (value) => String(value)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");

const truncateText = (value, wordLimit, charLimit) => {
    const text = String(value).trim();
    const words = text.split(/\s+/).filter(Boolean);

    if (words.length > wordLimit) {
        return `${words.slice(0, wordLimit).join(' ')}...`;
    }

    if (text.length > charLimit) {
        return `${text.slice(0, charLimit)}...`;
    }

    return text;
};

const allowedStatuses = ['pending', 'active', 'done'];

const todayDate = () => {
    const today = new Date();
    const offset = today.getTimezoneOffset() * 60000;
    return new Date(today.getTime() - offset).toISOString().split('T')[0];
};

const validateProject = ({ name, desc, budget, date, status }) => {
    const cleanName = String(name).trim();
    const cleanDesc = String(desc).trim();
    const cleanBudget = Number(budget);
    const errors = {};

    if (!cleanName) {
        errors.name = 'Project title is required.';
    } else if (!/^[\p{L}\p{N}]/u.test(cleanName)) {
        errors.name = 'Project title must start with a letter or number.';
    } else if (cleanName.length > 80) {
        errors.name = 'Project title must be 80 characters or less.';
    }

    if (cleanDesc.length > 240) {
        errors.desc = 'Description must be 240 characters or less.';
    }

    if (!date) {
        errors.date = 'Start date is required.';
    } else if (date > todayDate()) {
        errors.date = 'Start date cannot be in the future.';
    }

    if (!Number.isFinite(cleanBudget) || cleanBudget <= 0) {
        errors.budget = 'Budget must be greater than zero.';
    }

    if (!allowedStatuses.includes(String(status).toLowerCase())) {
        errors.status = 'Status must be pending, active, or done.';
    }

    return errors;
};

const hasErrors = (errors) => Object.keys(errors).length > 0;

const clearFieldErrors = (scope) => {
    scope.querySelectorAll('.field-error').forEach(error => error.remove());
    scope.querySelectorAll('.input-error').forEach(input => input.classList.remove('input-error'));
};

const showFieldError = (field, message) => {
    if (!field || !message) {
        return;
    }

    field.classList.add('input-error');

    const error = document.createElement('div');
    error.className = 'field-error';
    error.textContent = message;
    field.insertAdjacentElement('afterend', error);
};

const showModalErrors = (errors) => {
    clearFieldErrors(modal);
    showFieldError(document.querySelector('.Pname'), errors.name);
    showFieldError(document.querySelector('.Pdesc'), errors.desc);
    showFieldError(document.querySelector('.startDate'), errors.date);
    showFieldError(document.querySelector('.budget'), errors.budget);
    showFieldError(document.querySelector('.status'), errors.status);
};

const showRowErrors = (row, errors) => {
    clearFieldErrors(row);
    showFieldError(row.querySelector('.edit-name'), errors.name);
    showFieldError(row.querySelector('.edit-desc'), errors.desc);
    showFieldError(row.querySelector('.edit-date'), errors.date);
    showFieldError(row.querySelector('.edit-budget'), errors.budget);
    showFieldError(row.querySelector('.edit-status'), errors.status);
};

// 2. Show Modal
const showModal = (e) => {
    e.preventDefault();
    if (modal && overlay) {
       clearFieldErrors(modal);
       overlay.classList.add("view");
    }
};

// 3. Hide Modal
const hideModal = (e) => {
    if(e) e.preventDefault();
    if (modal && overlay) {
        overlay.classList.remove("view");
    }
};

// 4. Save to Database
const saveInfo = (e) => {
    e.preventDefault();

    const pName = document.querySelector(".Pname").value;
    const pDesc = document.querySelector(".Pdesc").value;
    const pDate = document.querySelector(".startDate").value;
    const pBudget = document.querySelector(".budget").value;
    const pStatus = 'pending';

    const validationErrors = validateProject({
        name: pName,
        desc: pDesc,
        budget: pBudget,
        date: pDate,
        status: pStatus
    });

    if (hasErrors(validationErrors)) {
        showModalErrors(validationErrors);
        return;
    }

    let formData = new FormData();
    formData.append('Pname', pName);
    formData.append('Pdesc', pDesc);
    formData.append('startDate', pDate);
    formData.append('budget', pBudget);
    formData.append('status', pStatus);

    fetch('../actions/save_project.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        if (data.trim() === "success") {
            location.reload();
        } else {
            showModalErrors({ name: data.trim() || 'Could not save project.' });
        }
    })
    .catch(error => console.error("Error:", error));
};


//to delete the row
document.querySelectorAll('#delete-btn').forEach(btn => {
    btn.addEventListener('click', function() {
            const row = this.closest('tr');
            const projectId = row.getAttribute('data-id');

            // AJAX request 
            fetch('../actions/project_action.php', {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id=' + projectId
            })
            .then(response => response.text())
            .then(data => {
                if (data === "success") {
                    location.reload(); // refresh stats boxes too
                } else {
                    console.error("Error deleting project:", data);
                }
            });
        
    });
});
//to change the iconssss

document.querySelectorAll('.edit-trigger').forEach(trigger => {
    trigger.addEventListener('click', function() {
        const row = this.closest('tr');
        const isEditing = this.querySelector('.lucide-pencil');
        
        console.log("Edit clicked for row ID:", row.getAttribute('data-id'));

        if (isEditing) {
            // 1. Ghayyer l-Icon la Check (Khadra)
            this.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-check save-icon"><path d="M20 6 9 17l-5-5"/></svg>`;
            
            // 2. 7awwel l-cells la Inputs
            // Name
            let nameVal = row.dataset.name || row.cells[0].innerText.trim();
            row.cells[0].innerHTML = `<input type="text" class="edit-input edit-name" maxlength="80" value="${escapeHTML(nameVal)}">`;
            
            // Description
            let descVal = row.dataset.desc || row.cells[1].innerText.trim();
            row.cells[1].innerHTML = `<input type="text" class="edit-input edit-desc" maxlength="240" value="${escapeHTML(descVal)}">`;
            
            // Budget (Shelna l-fawasil w l-$)
            let budgetVal = row.cells[2].innerText.replace(/[$,]/g, '').trim();
            row.cells[2].innerHTML = `<input type="number" class="edit-input edit-budget" value="${escapeHTML(budgetVal)}">`;
            
            // Date
            let dateVal = row.cells[3].innerText.replace('$', '').trim();
            row.cells[3].innerHTML = `<input type="date" class="edit-input edit-date" max="${todayDate()}" value="${escapeHTML(dateVal)}">`;

            // Status
            let statusVal = row.cells[4].innerText.trim().toLowerCase();
            row.cells[4].innerHTML = `
                <select class="edit-input edit-status">
                    <option value="pending" ${statusVal === 'pending' ? 'selected' : ''}>Pending</option>
                    <option value="active" ${statusVal === 'active' ? 'selected' : ''}>Active</option>
                    <option value="done" ${statusVal === 'done' ? 'selected' : ''}>Done</option>
                </select>`;
            
            console.log("Inputs generated successfully");

        } else {
            // Mode: SAVE
            saveRowData(row, this);
        }
    });
});
function saveRowData(row, trigger) {
    const projectId = row.getAttribute('data-id');
    
    const updatedData = {
        id: projectId,
        name: row.querySelector('.edit-name').value,
        desc: row.querySelector('.edit-desc').value,
        budget: row.querySelector('.edit-budget').value,
        date: row.querySelector('.edit-date').value,
        status: row.querySelector('.edit-status').value
    };

    const validationErrors = validateProject(updatedData);

    if (hasErrors(validationErrors)) {
        showRowErrors(row, validationErrors);
        return;
    }

    // Hon bta3mel fetch lal file el-jdid taba3ak
    fetch('../actions/project_action.php', { 
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(updatedData)
    })
    .then(response => response.text())
    .then(data => {
        if (data.trim() === "success") {
            // Raje3 l-text 3al table metel ma ken ma l-data l-jdide
            row.dataset.name = updatedData.name;
            row.dataset.desc = updatedData.desc;
            row.cells[0].innerHTML = `<a href="details.php?id=${projectId}" class="project-link"><p style="color: #111827; font-weight: bold;">${escapeHTML(truncateText(updatedData.name, 2, 14))}</p></a>`;
            row.cells[1].innerHTML = `<p class="grey">${escapeHTML(truncateText(updatedData.desc, 8, 55))}</p>`;
            row.cells[2].innerText = "$" + Number(updatedData.budget).toLocaleString();
            row.cells[3].innerText =  updatedData.date;
            row.cells[4].innerHTML = `<p class="statusColor ${updatedData.status.toLowerCase()}">${updatedData.status}</p>`;
            
            // Raje3 l-icon la pencil
            trigger.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#d2ce00" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-pencil edit-icon"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/><path d="m15 5 4 4"/></svg>`;
        }
    });
}

// 5. Event Listeners
if (addProjectBtn) addProjectBtn.addEventListener("click", showModal);
if (cancelBtn) cancelBtn.addEventListener("click", hideModal);
if (closeX) closeX.addEventListener("click", hideModal);
if (createBtn) createBtn.addEventListener("click", saveInfo);

if (overlay) {
    overlay.addEventListener("click", (e) => {
        if (e.target === overlay) hideModal();
    });
}
