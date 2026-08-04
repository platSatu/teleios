/*
Template Name: Mirbal - Admin & Dashboard Template
Author: SRBThemes
File: Social View init js
*/

// Print mail preview
function printMailPreview() {
  const printContent = document.getElementById('mailPreviewSection').innerHTML;
  const originalContent = document.body.innerHTML;

  // Replace body with mail preview only
  document.body.innerHTML = printContent;
  window.print();

  // Restore original content
  document.body.innerHTML = originalContent;

  // Re-initialize scripts if needed
  location.reload(); // reload to restore JS interactions
}