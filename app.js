// Client-side interactions for Campus Lost & Found

// Confirm dangerous actions (e.g. Delete listing)
document.addEventListener('click', function (ev) {
  var b = ev.target.closest('[data-confirm]');
  if (b && !window.confirm(b.getAttribute('data-confirm'))) {
    ev.preventDefault();
  }
});

// Image preview on post.php
document.addEventListener('DOMContentLoaded', function () {
  var photoInput = document.getElementById('photoFileInput');
  var previewBox = document.getElementById('photoPreviewBox');
  var previewImg = document.getElementById('photoPreviewImg');

  if (photoInput && previewBox && previewImg) {
    photoInput.addEventListener('change', function () {
      var file = this.files && this.files[0];
      if (file) {
        // Nielsen Heuristic #5: Client-side check preventing oversized upload (> 2 MB)
        if (file.size > 2 * 1024 * 1024) {
          alert('Warning: The selected image is ' + (file.size / (1024 * 1024)).toFixed(1) + ' MB. Maximum allowed size is 2.0 MB. Please choose a smaller photo.');
          this.value = '';
          previewBox.style.display = 'none';
          previewImg.src = '';
          return;
        }

        if (file.type.startsWith('image/')) {
          var reader = new FileReader();
          reader.onload = function (e) {
            previewImg.src = e.target.result;
            previewBox.style.display = 'block';
          };
          reader.readAsDataURL(file);
        }
      } else {
        previewBox.style.display = 'none';
        previewImg.src = '';
      }
    });
  }

  // Notification badge: add aria-live so screen readers announce unread count
  var badge = document.querySelector('.badge-count');
  if (badge && parseInt(badge.textContent, 10) > 0) {
    badge.setAttribute('aria-live', 'polite');
    badge.setAttribute('aria-label', badge.textContent + ' unread notifications');
  }
});
