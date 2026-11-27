// Back to the site once the phone is online again (the site chosen on the first screen).
function retry() {
  var site = null;
  try { site = localStorage.getItem('vis.site'); } catch (e) { /* none */ }
  location.href = site ? site + '/login' : 'index.html';
}
document.getElementById('retry').addEventListener('click', retry);
window.addEventListener('online', retry);
