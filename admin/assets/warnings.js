document.addEventListener('click', (event) => {
 const button = event.target.closest('.wpdl-warning .notice-dismiss');
 if (!button) return;
 const notice = button.closest('.wpdl-warning');
 fetch(wpdlWarnings.root + notice.dataset.wpdlId + '/dismiss', {method:'POST',headers:{'X-WP-Nonce':wpdlWarnings.nonce}}).then(response => { if (!response.ok) console.error('DecisionLog warning dismissal could not be saved.'); }).catch(() => console.error('DecisionLog warning dismissal could not be saved.'));
});
