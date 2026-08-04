/*
Template Name: Mirbal - Admin & Dashboard Template
Author: SRBThemes
File: Social Setting init js
*/

document.addEventListener('DOMContentLoaded', function () {
    let profileVisibilityChoice = document.getElementById('profileVisibility');
    if (profileVisibilityChoice) {
        const choices = new Choices('#profileVisibility', {
            placeholderValue: 'Select Type',
            searchPlaceholderValue: 'Search...',
            removeItemButton: true,
            itemSelectText: 'Press to select',
        });
    }
    let followersVisibilityChoice = document.getElementById('followersVisibility');
    if (followersVisibilityChoice) {
        const choices = new Choices('#followersVisibility', {
            placeholderValue: 'Select Type',
            searchPlaceholderValue: 'Search...',
            removeItemButton: true,
            itemSelectText: 'Press to select',
        });
    }
    let friendsVisibilityChoice = document.getElementById('friendsVisibility');
    if (friendsVisibilityChoice) {
        const choices = new Choices('#friendsVisibility', {
            placeholderValue: 'Select Type',
            searchPlaceholderValue: 'Search...',
            removeItemButton: true,
            itemSelectText: 'Press to select',
        });
    }
});

  // Toggle all password fields
  document.querySelectorAll('.toggle-password').forEach(btn => {
    btn.addEventListener('click', function () {
      const targetId = this.getAttribute('data-target');
      const input = document.getElementById(targetId);
      const icon = this.querySelector('i');

      if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('ri-eye-off-line');
        icon.classList.add('ri-eye-line');
      } else {
        input.type = 'password';
        icon.classList.remove('ri-eye-line');
        icon.classList.add('ri-eye-off-line');
      }
    });
  });