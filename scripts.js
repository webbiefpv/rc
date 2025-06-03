function editModel(id, name) {
    document.getElementById('edit_model_id').value = id;
    document.getElementById('edit_name').value = name;
    new bootstrap.Modal(document.getElementById('editModelModal')).show();
}

function editSetup(id, name) {
    document.getElementById('edit_setup_id').value = id;
    document.getElementById('edit_name').value = name;
    new bootstrap.Modal(document.getElementById('editSetupModal')).show();
}

function shareSetup() {
    const url = window.location.href;
    if (navigator.share) {
        navigator.share({
            title: 'Pan Car Setup',
            url: url
        }).catch(console.error);
    } else {
        alert('Share not supported. Copy this URL: ' + url);
    }
}