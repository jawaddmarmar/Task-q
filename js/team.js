const teamOverlay = document.getElementById('team-modal-overlay');
const memberOverlay = document.getElementById('member-modal-overlay');
const addTeamBtn = document.getElementById('add_team_btn');
const addMemberBtn = document.getElementById('add_member_btn');

const clearErrors = (scope) => {
    scope.querySelectorAll('.field-error').forEach(error => error.remove());
    scope.querySelectorAll('.input-error').forEach(input => input.classList.remove('input-error'));
};

const showError = (field, message) => {
    if (!field || !message) return;
    field.classList.add('input-error');
    const oldError = field.parentElement?.querySelector('.field-error');
    if (oldError) oldError.remove();
    const error = document.createElement('div');
    error.className = 'field-error';
    error.textContent = message;
    field.insertAdjacentElement('afterend', error);
};

const toggle = (overlay, show) => {
    clearErrors(overlay);
    overlay.classList[show ? 'add' : 'remove']('view');
};

const postTeamAction = (body) => fetch('../actions/team_action.php', {
    method: 'POST',
    credentials: 'same-origin',
    cache: 'no-store',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body
}).then(response => {
    if (response.redirected) {
        window.location.href = response.url;
        return Promise.reject(new Error('Redirected'));
    }
    return response.text();
});

const validateTeam = () => {
    const errors = {};
    const name = document.getElementById('teamName').value.trim();
    const desc = document.getElementById('teamDesc').value.trim();

    if (!name) errors.name = 'Team name is required.';
    else if (!/^[\p{L}\p{N}]/u.test(name)) errors.name = 'Team name must start with a letter or number.';
    else if (name.length > 120) errors.name = 'Team name must be 120 characters or less.';
    if (desc.length > 240) errors.desc = 'Description must be 240 characters or less.';

    return errors;
};

const validateMember = () => {
    const errors = {};
    const fullName = document.getElementById('memberFullName').value.trim();
    const userName = document.getElementById('memberUserName').value.trim();
    const email = document.getElementById('memberEmail').value.trim();
    const role = document.getElementById('memberRole').value.trim();
    const department = document.getElementById('memberDepartment').value.trim();

    if (!fullName) errors.fullName = 'Full name is required.';
    else if (!/^[\p{L}\p{N}]/u.test(fullName)) errors.fullName = 'Full name must start with a letter or number.';
    if (!/^[A-Za-z0-9._-]+$/.test(userName)) errors.userName = 'Use letters, numbers, dot, dash, or underscore.';
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) errors.email = 'Valid email is required.';
    if (!role) errors.role = 'Role is required.';
    if (!department) errors.department = 'Department is required.';

    return errors;
};

document.getElementById('team-create')?.addEventListener('click', () => {
    const errors = validateTeam();
    if (Object.keys(errors).length) {
        clearErrors(teamOverlay);
        showError(document.getElementById('teamName'), errors.name);
        showError(document.getElementById('teamDesc'), errors.desc);
        return;
    }

    const selectedMembers = Array.from(document.querySelectorAll('.member-picker input:checked')).map(input => input.value);
    const body = new URLSearchParams({
        team_name: document.getElementById('teamName').value,
        desc: document.getElementById('teamDesc').value
    });
    selectedMembers.forEach(memberId => body.append('members[]', memberId));

    fetch('../actions/save_team.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body
    })
    .then(response => response.text())
    .then(result => {
        if (result.trim() === 'success') location.reload();
        else showError(document.getElementById('teamName'), result.trim() || 'Could not create team.');
    });
});

document.getElementById('member-create')?.addEventListener('click', () => {
    const errors = validateMember();
    if (Object.keys(errors).length) {
        clearErrors(memberOverlay);
        showError(document.getElementById('memberFullName'), errors.fullName);
        showError(document.getElementById('memberUserName'), errors.userName);
        showError(document.getElementById('memberEmail'), errors.email);
        showError(document.getElementById('memberRole'), errors.role);
        showError(document.getElementById('memberDepartment'), errors.department);
        return;
    }

    fetch('../actions/save_member.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            full_name: document.getElementById('memberFullName').value,
            username: document.getElementById('memberUserName').value,
            email: document.getElementById('memberEmail').value,
            role: document.getElementById('memberRole').value,
            department: document.getElementById('memberDepartment').value
        })
    })
    .then(response => response.text())
    .then(result => {
        if (result.trim() === 'success') location.reload();
        else showError(document.getElementById('memberEmail'), result.trim() || 'Could not create member.');
    });
});

addTeamBtn?.addEventListener('click', () => toggle(teamOverlay, true));
addMemberBtn?.addEventListener('click', () => toggle(memberOverlay, true));
document.getElementById('team-close-x')?.addEventListener('click', () => toggle(teamOverlay, false));
document.getElementById('team-cancel')?.addEventListener('click', () => toggle(teamOverlay, false));
document.getElementById('member-close-x')?.addEventListener('click', () => toggle(memberOverlay, false));
document.getElementById('member-cancel')?.addEventListener('click', () => toggle(memberOverlay, false));

document.querySelectorAll('.team-delete-btn').forEach(button => {
    button.addEventListener('click', () => {
        button.disabled = true;
        postTeamAction(new URLSearchParams({
            action: 'delete',
            team_id: button.dataset.teamId
        }))
        .then(result => {
            if (result.trim() === 'success') {
                location.reload();
            } else {
                button.disabled = false;
                const card = button.closest('.team-card');
                showError(button, result.trim().replace(/<[^>]*>/g, '') || 'Could not delete team.');
                if (card) card.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        })
        .catch(() => {
            button.disabled = false;
            showError(button, 'Could not delete team.');
        });
    });
});

document.querySelectorAll('.member-delete-btn').forEach(button => {
    button.addEventListener('click', () => {
        button.disabled = true;
        postTeamAction(new URLSearchParams({
            action: 'delete_member',
            member_id: button.dataset.memberId
        }))
        .then(result => {
            if (result.trim() === 'success') {
                location.reload();
            } else {
                button.disabled = false;
                showError(button, result.trim().replace(/<[^>]*>/g, '') || 'Could not delete member.');
            }
        })
        .catch(() => {
            button.disabled = false;
            showError(button, 'Could not delete member.');
        });
    });
});

[teamOverlay, memberOverlay].filter(Boolean).forEach(overlay => {
    overlay.addEventListener('click', event => {
        if (event.target === overlay) toggle(overlay, false);
    });
});
