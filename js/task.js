const taskOverlay = document.getElementById('task-modal-overlay');
const taskModal = document.getElementById('task-modal-box');
const addTaskBtn = document.getElementById('add_task_btn');
const cancelTaskBtn = document.getElementById('task-btn-cancel');
const closeTaskX = document.getElementById('task-close-x');
const createTaskBtn = document.getElementById('task-btn-create');

const taskFields = {
    title: () => document.getElementById('taskTitle'),
    desc: () => document.getElementById('taskDesc'),
    project: () => document.getElementById('taskProject'),
    team: () => document.getElementById('taskTeam'),
    user: () => document.getElementById('taskUser'),
    dueDate: () => document.getElementById('taskDueDate'),
    priority: () => document.getElementById('taskPriority'),
    status: () => document.getElementById('taskStatus')
};

const escapeHTML = (value) => String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

const todayDate = () => {
    const today = new Date();
    const offset = today.getTimezoneOffset() * 60000;
    return new Date(today.getTime() - offset).toISOString().split('T')[0];
};

const validateTask = ({ title, desc, project_id, due_date, priority, status }) => {
    const errors = {};
    const allowedPriorities = ['low', 'medium', 'high', 'critical'];
    const allowedStatuses = ['pending', 'active', 'done'];
    const cleanTitle = String(title).trim();
    const cleanDesc = String(desc).trim();

    if (!cleanTitle) {
        errors.title = 'Task title is required.';
    } else if (!/^[\p{L}\p{N}]/u.test(cleanTitle)) {
        errors.title = 'Task title must start with a letter or number.';
    } else if (cleanTitle.length > 90) {
        errors.title = 'Task title must be 90 characters or less.';
    }

    if (cleanDesc.length > 300) {
        errors.desc = 'Description must be 300 characters or less.';
    }

    if (!project_id) {
        errors.project = 'Please select a project.';
    }

    if (!due_date) {
        errors.dueDate = 'Due date is required.';
    } else if (due_date < todayDate()) {
        errors.dueDate = 'Due date cannot be in the past.';
    }

    if (!allowedPriorities.includes(String(priority).toLowerCase())) {
        errors.priority = 'Choose low, medium, high, or critical.';
    }

    if (!allowedStatuses.includes(String(status).toLowerCase())) {
        errors.status = 'Choose pending, active, or done.';
    }

    return errors;
};

const hasErrors = (errors) => Object.keys(errors).length > 0;

const clearFieldErrors = (scope) => {
    scope.querySelectorAll('.field-error').forEach(error => error.remove());
    scope.querySelectorAll('.input-error').forEach(input => input.classList.remove('input-error'));
};

const showFieldError = (field, message) => {
    if (!field || !message) return;

    field.classList.add('input-error');
    const error = document.createElement('div');
    error.className = 'field-error';
    error.textContent = message;
    field.insertAdjacentElement('afterend', error);
};

const showModalErrors = (errors) => {
    clearFieldErrors(taskModal);
    showFieldError(taskFields.title(), errors.title);
    showFieldError(taskFields.desc(), errors.desc);
    showFieldError(taskFields.project(), errors.project);
    showFieldError(taskFields.dueDate(), errors.dueDate);
    showFieldError(taskFields.priority(), errors.priority);
    showFieldError(taskFields.status(), errors.status);
};

const showRowErrors = (row, errors) => {
    clearFieldErrors(row);
    showFieldError(row.querySelector('.task-edit-title'), errors.title);
    showFieldError(row.querySelector('.task-edit-desc'), errors.desc);
    showFieldError(row.querySelector('.task-edit-project'), errors.project);
    showFieldError(row.querySelector('.task-edit-date'), errors.dueDate);
    showFieldError(row.querySelector('.task-edit-priority'), errors.priority);
    showFieldError(row.querySelector('.task-edit-status'), errors.status);
};

const getTaskDataFromModal = () => ({
    title: taskFields.title().value,
    desc: taskFields.desc().value,
    project_id: taskFields.project().value,
    team_id: taskFields.team().value,
    user_id: taskFields.user().value,
    due_date: taskFields.dueDate().value,
    priority: taskFields.priority().value,
    status: taskFields.status().value
});

const openTaskModal = (event) => {
    event.preventDefault();
    clearFieldErrors(taskModal);
    taskOverlay.classList.add('view');
};

const closeTaskModal = (event) => {
    if (event) event.preventDefault();
    taskOverlay.classList.remove('view');
};

const createTask = (event) => {
    event.preventDefault();
    const taskData = getTaskDataFromModal();
    const validationErrors = validateTask(taskData);

    if (hasErrors(validationErrors)) {
        showModalErrors(validationErrors);
        return;
    }

    fetch('../actions/save_task.php', {
        method: 'POST',
        body: new URLSearchParams(taskData)
    })
    .then(response => response.text())
    .then(data => {
        if (data.trim() === 'success') {
            location.reload();
        } else {
            showModalErrors({ title: data.trim() || 'Could not save task.' });
        }
    });
};

const getSelectOptions = (selector, selectedValue = '') => {
    const source = document.querySelector(selector);
    return Array.from(source.options).map(option => (
        `<option value="${escapeHTML(option.value)}" ${String(option.value) === String(selectedValue) ? 'selected' : ''}>${escapeHTML(option.textContent)}</option>`
    )).join('');
};

const makeTaskEditable = (row, trigger) => {
    trigger.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-check save-icon"><path d="M20 6 9 17l-5-5"/></svg>`;

    row.cells[0].innerHTML = `<input type="text" class="edit-input task-edit-title" maxlength="90" value="${escapeHTML(row.dataset.title || row.cells[0].innerText.trim())}">`;
    row.cells[1].innerHTML = `<select class="edit-input task-edit-project">${getSelectOptions('#taskProject', row.dataset.projectId)}</select>`;
    row.cells[2].innerHTML = `<select class="edit-input task-edit-team">${getSelectOptions('#taskTeam', row.dataset.teamId)}</select>`;
    row.cells[3].innerHTML = `<input type="text" class="edit-input task-edit-desc" maxlength="300" value="${escapeHTML(row.dataset.desc || row.cells[3].innerText.trim())}">`;
    row.cells[4].innerHTML = `<select class="edit-input task-edit-user">${getSelectOptions('#taskUser', row.dataset.userId)}</select>`;
    row.cells[5].innerHTML = `
        <select class="edit-input task-edit-status">
            <option value="pending" ${row.cells[5].innerText.trim().toLowerCase() === 'pending' ? 'selected' : ''}>Pending</option>
            <option value="active" ${row.cells[5].innerText.trim().toLowerCase() === 'active' ? 'selected' : ''}>Active</option>
            <option value="done" ${row.cells[5].innerText.trim().toLowerCase() === 'done' ? 'selected' : ''}>Done</option>
        </select>`;
    row.cells[6].innerHTML = `
        <select class="edit-input task-edit-priority">
            <option value="low" ${row.cells[6].innerText.trim().toLowerCase() === 'low' ? 'selected' : ''}>Low</option>
            <option value="medium" ${row.cells[6].innerText.trim().toLowerCase() === 'medium' ? 'selected' : ''}>Medium</option>
            <option value="high" ${row.cells[6].innerText.trim().toLowerCase() === 'high' ? 'selected' : ''}>High</option>
            <option value="critical" ${row.cells[6].innerText.trim().toLowerCase() === 'critical' ? 'selected' : ''}>Critical</option>
        </select>`;
    row.cells[7].innerHTML = `<input type="date" class="edit-input task-edit-date" min="${todayDate()}" value="${escapeHTML(row.cells[7].innerText.trim())}">`;
};

const saveTaskRow = (row) => {
    const taskData = {
        id: row.dataset.id,
        title: row.querySelector('.task-edit-title').value,
        desc: row.querySelector('.task-edit-desc').value,
        project_id: row.querySelector('.task-edit-project').value,
        team_id: row.querySelector('.task-edit-team').value,
        user_id: row.querySelector('.task-edit-user').value,
        due_date: row.querySelector('.task-edit-date').value,
        priority: row.querySelector('.task-edit-priority').value,
        status: row.querySelector('.task-edit-status').value
    };
    const validationErrors = validateTask(taskData);

    if (hasErrors(validationErrors)) {
        showRowErrors(row, validationErrors);
        return;
    }

    fetch('../actions/task_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(taskData)
    })
    .then(response => response.text())
    .then(data => {
        if (data.trim() === 'success') {
            location.reload();
        } else {
            showRowErrors(row, { title: data.trim() || 'Could not update task.' });
        }
    });
};

document.querySelectorAll('.task-edit-trigger').forEach(trigger => {
    trigger.addEventListener('click', function() {
        const row = this.closest('tr');
        const isEditing = this.querySelector('.lucide-pencil');

        if (isEditing) {
            makeTaskEditable(row, this);
        } else {
            saveTaskRow(row);
        }
    });
});

document.querySelectorAll('.task-delete-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const row = this.closest('tr');
        fetch('../actions/task_action.php', {
            method: 'POST',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ id: row.dataset.id })
        })
        .then(response => response.text())
        .then(data => {
            if (data.trim() === 'success') {
                location.reload();
            } else {
                console.error('Error deleting task:', data);
            }
        });
    });
});

if (addTaskBtn) addTaskBtn.addEventListener('click', openTaskModal);
if (cancelTaskBtn) cancelTaskBtn.addEventListener('click', closeTaskModal);
if (closeTaskX) closeTaskX.addEventListener('click', closeTaskModal);
if (createTaskBtn) createTaskBtn.addEventListener('click', createTask);
if (taskOverlay) {
    taskOverlay.addEventListener('click', event => {
        if (event.target === taskOverlay) closeTaskModal();
    });
}
