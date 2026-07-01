const assignmentGrid = document.querySelector('.project-assignment-grid[data-project-id]');

const showAssignmentError = (field, message) => {
    field.classList.add('input-error');
    const oldError = field.parentElement.querySelector('.field-error');
    if (oldError) oldError.remove();

    const error = document.createElement('div');
    error.className = 'field-error';
    error.textContent = message;
    field.parentElement.appendChild(error);
};

const assignProjectRelation = (type, selectId) => {
    const select = document.getElementById(selectId);
    const targetId = select.value;

    select.classList.remove('input-error');
    const oldError = select.parentElement.querySelector('.field-error');
    if (oldError) oldError.remove();

    if (!targetId) {
        showAssignmentError(select, `Please select a ${type}.`);
        return;
    }

    fetch('../actions/project_relation_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            project_id: assignmentGrid.dataset.projectId,
            type,
            target_id: targetId
        })
    })
    .then(response => response.text())
    .then(result => {
        if (result.trim() === 'success') {
            location.reload();
        } else {
            showAssignmentError(select, result.trim() || 'Could not add assignment.');
        }
    });
};

document.getElementById('assignTeamBtn')?.addEventListener('click', () => assignProjectRelation('team', 'projectTeamSelect'));
document.getElementById('assignMemberBtn')?.addEventListener('click', () => assignProjectRelation('member', 'projectMemberSelect'));
