const teamId = document.getElementById('detailTeamId')?.value;
const editOverlay = document.getElementById('edit-team-overlay');
const addMemberOverlay = document.getElementById('add-team-member-overlay');

const clearErrors = (scope) => {
    scope.querySelectorAll('.field-error').forEach(error => error.remove());
    scope.querySelectorAll('.input-error').forEach(input => input.classList.remove('input-error'));
};

const showError = (field, message) => {
    if (!field || !message) return;

    field.classList.add('input-error');
    const oldError = field.parentElement.querySelector('.field-error');
    if (oldError) oldError.remove();

    const error = document.createElement('div');
    error.className = 'field-error';
    error.textContent = message;
    field.insertAdjacentElement('afterend', error);
};

const toggleOverlay = (overlay, show) => {
    clearErrors(overlay);
    overlay.classList[show ? 'add' : 'remove']('view');
};

const postTeamAction = (data, errorField) => {
    fetch('../actions/team_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(data)
    })
    .then(response => response.text())
    .then(result => {
        if (result.trim() === 'success') {
            location.reload();
        } else {
            showError(errorField, result.trim() || 'Could not update team.');
        }
    });
};

document.getElementById('edit_team_btn')?.addEventListener('click', () => toggleOverlay(editOverlay, true));
document.getElementById('edit-team-close')?.addEventListener('click', () => toggleOverlay(editOverlay, false));
document.getElementById('edit-team-cancel')?.addEventListener('click', () => toggleOverlay(editOverlay, false));

document.getElementById('add_team_member_btn')?.addEventListener('click', () => toggleOverlay(addMemberOverlay, true));
document.getElementById('add-team-member-close')?.addEventListener('click', () => toggleOverlay(addMemberOverlay, false));
document.getElementById('add-team-member-cancel')?.addEventListener('click', () => toggleOverlay(addMemberOverlay, false));

document.getElementById('edit-team-save')?.addEventListener('click', () => {
    const nameField = document.getElementById('detailTeamName');
    const descField = document.getElementById('detailTeamDesc');
    const name = nameField.value.trim();
    const desc = descField.value.trim();

    clearErrors(editOverlay);

    if (!name) {
        showError(nameField, 'Team name is required.');
        return;
    }

    if (!/^[\p{L}\p{N}]/u.test(name)) {
        showError(nameField, 'Team name must start with a letter or number.');
        return;
    }

    if (desc.length > 240) {
        showError(descField, 'Description must be 240 characters or less.');
        return;
    }

    postTeamAction({
        action: 'update',
        team_id: teamId,
        team_name: name,
        desc
    }, nameField);
});

document.getElementById('add-team-member-save')?.addEventListener('click', () => {
    const select = document.getElementById('teamMemberSelect');
    clearErrors(addMemberOverlay);

    if (!select.value) {
        showError(select, 'Please select a member.');
        return;
    }

    postTeamAction({
        action: 'add_member',
        team_id: teamId,
        member_id: select.value
    }, select);
});

document.querySelectorAll('.remove-member-btn').forEach(button => {
    button.addEventListener('click', () => {
        postTeamAction({
            action: 'remove_member',
            team_id: teamId,
            member_id: button.dataset.memberId
        }, button);
    });
});

[editOverlay, addMemberOverlay].filter(Boolean).forEach(overlay => {
    overlay.addEventListener('click', event => {
        if (event.target === overlay) toggleOverlay(overlay, false);
    });
});
