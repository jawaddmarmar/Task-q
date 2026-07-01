const overlay = document.getElementById('task-detail-modal-overlay');
const modal = document.getElementById('task-detail-modal-box');
const editBtn = document.getElementById('edit_task_trigger');
const closeBtn = document.getElementById('task-detail-close-x');
const cancelBtn = document.getElementById('task-detail-cancel');
const saveBtn = document.getElementById('task-detail-save');

const todayDate = () => {
    const today = new Date();
    const offset = today.getTimezoneOffset() * 60000;
    return new Date(today.getTime() - offset).toISOString().split('T')[0];
};

const clearFieldErrors = () => {
    modal.querySelectorAll('.field-error').forEach(error => error.remove());
    modal.querySelectorAll('.input-error').forEach(input => input.classList.remove('input-error'));
};

const showFieldError = (field, message) => {
    if (!field || !message) return;

    field.classList.add('input-error');
    const error = document.createElement('div');
    error.className = 'field-error';
    error.textContent = message;
    field.insertAdjacentElement('afterend', error);
};

const validateTask = (data) => {
    const errors = {};
    const cleanTitle = data.title.trim();
    const cleanDesc = data.desc.trim();

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

    if (!data.project_id) {
        errors.project = 'Please select a project.';
    }

    if (!data.due_date) {
        errors.due = 'Due date is required.';
    } else if (data.due_date < todayDate()) {
        errors.due = 'Due date cannot be in the past.';
    }

    if (!['low', 'medium', 'high', 'critical'].includes(data.priority)) {
        errors.priority = 'Choose a valid priority.';
    }

    if (!['pending', 'active', 'done'].includes(data.status)) {
        errors.status = 'Choose a valid status.';
    }

    return errors;
};

const showErrors = (errors) => {
    clearFieldErrors();
    showFieldError(document.getElementById('edit_task_title'), errors.title);
    showFieldError(document.getElementById('edit_task_desc'), errors.desc);
    showFieldError(document.getElementById('edit_task_project'), errors.project);
    showFieldError(document.getElementById('edit_task_due'), errors.due);
    showFieldError(document.getElementById('edit_task_priority'), errors.priority);
    showFieldError(document.getElementById('edit_task_status'), errors.status);
};

const toggleModal = (show) => overlay?.classList[show ? 'add' : 'remove']('view');

editBtn?.addEventListener('click', () => {
    clearFieldErrors();
    toggleModal(true);
});
closeBtn?.addEventListener('click', () => toggleModal(false));
cancelBtn?.addEventListener('click', () => toggleModal(false));

saveBtn?.addEventListener('click', () => {
    const data = {
        id: document.getElementById('edit_task_id').value,
        title: document.getElementById('edit_task_title').value,
        desc: document.getElementById('edit_task_desc').value,
        project_id: document.getElementById('edit_task_project').value,
        team_id: document.getElementById('edit_task_team').value,
        user_id: document.getElementById('edit_task_user').value,
        due_date: document.getElementById('edit_task_due').value,
        priority: document.getElementById('edit_task_priority').value,
        status: document.getElementById('edit_task_status').value
    };
    const errors = validateTask(data);

    if (Object.keys(errors).length) {
        showErrors(errors);
        return;
    }

    fetch('../actions/task_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(data)
    })
    .then(response => response.text())
    .then(result => {
        if (result.trim() === 'success') {
            location.reload();
        } else {
            showErrors({ title: result.trim() || 'Could not update task.' });
        }
    });
});

const commentForm = document.querySelector('.comment-form');
const addCommentBtn = document.getElementById('addTaskCommentBtn');
const commentText = document.getElementById('newTaskComment');

const clearCommentError = () => {
    commentText.classList.remove('input-error');
    const error = commentForm.querySelector('.field-error');
    if (error) error.remove();
};

const showCommentError = (message) => {
    clearCommentError();
    commentText.classList.add('input-error');
    const error = document.createElement('div');
    error.className = 'field-error';
    error.textContent = message;
    commentText.insertAdjacentElement('afterend', error);
};

addCommentBtn?.addEventListener('click', () => {
    const comment = commentText.value.trim();
    clearCommentError();

    if (!comment) {
        showCommentError('Comment cannot be empty.');
        return;
    }

    if (comment.length > 500) {
        showCommentError('Comment must be 500 characters or less.');
        return;
    }

    fetch('../actions/save_task_comment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            task_id: commentForm.dataset.taskId,
            comment,
            commented_by: 'Admin',
            commented_role: 'Admin'
        })
    })
    .then(response => response.text())
    .then(result => {
        if (result.trim() === 'success') {
            location.reload();
        } else {
            showCommentError(result.trim() || 'Could not add comment.');
        }
    });
});

const memberStatusEditor = document.querySelector('.member-status-editor');
const memberStatusSave = document.getElementById('memberStatusSave');
const memberTaskStatus = document.getElementById('memberTaskStatus');

memberStatusSave?.addEventListener('click', () => {
    fetch('../actions/task_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            member_status_update: '1',
            id: memberStatusEditor.dataset.taskId,
            status: memberTaskStatus.value
        })
    })
    .then(response => response.text())
    .then(result => {
        if (result.trim() === 'success') {
            location.reload();
        } else {
            showFieldError(memberTaskStatus, result.trim() || 'Could not update status.');
        }
    });
});
