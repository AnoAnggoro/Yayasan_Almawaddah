// Disable Service Worker for now to avoid errors
// Service Worker will be enabled later when PWA is fully configured
/*
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    const baseUrl = window.location.origin + '/PROJECT/yayasan_almawaddah/';
    navigator.serviceWorker.register(baseUrl + 'sw.js')
      .then((registration) => {
        console.log('ServiceWorker registration successful');
      })
      .catch((err) => {
        console.log('ServiceWorker registration failed: ', err);
      });
  });
}
*/

// Close modal when clicking outside
window.addEventListener('click', function(event) {
  const modals = document.querySelectorAll('.modal');
  modals.forEach(modal => {
    if (event.target === modal) {
      modal.style.display = 'none';
    }
  });
});

// Prevent form double submission
document.addEventListener('DOMContentLoaded', function() {
  const forms = document.querySelectorAll('form');
  forms.forEach(form => {
    form.addEventListener('submit', function(e) {
      const submitBtn = this.querySelector('button[type="submit"]');
      if (submitBtn && !submitBtn.disabled) {
        submitBtn.disabled = true;
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Menyimpan...';
        setTimeout(() => {
          submitBtn.disabled = false;
          submitBtn.textContent = originalText;
        }, 3000);
      }
    });
  });
});

// Success/Error message auto hide
document.addEventListener('DOMContentLoaded', function() {
  const alerts = document.querySelectorAll('.alert');
  alerts.forEach(alert => {
    setTimeout(() => {
      alert.style.opacity = '0';
      alert.style.transition = 'opacity 0.5s';
      setTimeout(() => {
        alert.remove();
      }, 500);
    }, 5000);
  });
});

// Image preview function
function previewImage(input, previewId) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = function(e) {
      const preview = document.getElementById(previewId || 'preview_foto');
      if (preview) {
        preview.src = e.target.result;
      }
    }
    reader.readAsDataURL(input.files[0]);
  }
}

// Format currency input
function formatCurrency(input) {
  let value = input.value.replace(/\D/g, '');
  value = new Intl.NumberFormat('id-ID').format(value);
  input.value = value;
}

// Confirm delete action
function confirmDelete(message) {
  return confirm(message || 'Yakin ingin menghapus data ini?');
}

// Print function
function printPage() {
  window.print();
}

// Export to Excel
function exportToExcel(tableClass, filename) {
  const table = document.querySelector('.' + tableClass);
  if (!table) {
    console.error('Table not found');
    return;
  }
  
  const html = table.outerHTML;
  const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename || 'export_' + Date.now() + '.xls';
  a.click();
  URL.revokeObjectURL(url);
}

// Date formatting
function formatDate(dateString) {
  const options = { year: 'numeric', month: 'long', day: 'numeric' };
  return new Date(dateString).toLocaleDateString('id-ID', options);
}

// Number formatting
function formatNumber(number) {
  return new Intl.NumberFormat('id-ID').format(number);
}

// Validate email
function validateEmail(email) {
  const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return re.test(email);
}

// Validate phone number
function validatePhone(phone) {
  const re = /^[0-9]{10,13}$/;
  return re.test(phone.replace(/\D/g, ''));
}

// Show loading overlay
function showLoading() {
  let overlay = document.getElementById('loadingOverlay');
  if (!overlay) {
    overlay = document.createElement('div');
    overlay.id = 'loadingOverlay';
    overlay.innerHTML = '<div style="width:50px;height:50px;border:5px solid #f3f3f3;border-top:5px solid #10b981;border-radius:50%;animation:spin 1s linear infinite;"></div>';
    overlay.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:9999;';
    document.body.appendChild(overlay);
  }
  overlay.style.display = 'flex';
}

// Hide loading overlay
function hideLoading() {
  const overlay = document.getElementById('loadingOverlay');
  if (overlay) {
    overlay.style.display = 'none';
  }
}

// Toast notification
function showToast(message, type = 'info') {
  const toast = document.createElement('div');
  toast.className = 'toast toast-' + type;
  toast.textContent = message;
  toast.style.cssText = 'position:fixed;top:20px;right:20px;padding:15px 20px;background:#10b981;color:white;border-radius:8px;box-shadow:0 4px 6px rgba(0,0,0,0.1);z-index:9999;animation:slideIn 0.3s ease;';
  
  if (type === 'error') toast.style.background = '#ef4444';
  if (type === 'warning') toast.style.background = '#f59e0b';
  if (type === 'success') toast.style.background = '#10b981';
  
  document.body.appendChild(toast);
  
  setTimeout(() => {
    toast.style.animation = 'slideOut 0.3s ease';
    setTimeout(() => toast.remove(), 300);
  }, 3000);
}

// Debounce function for search
function debounce(func, wait) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
  @keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
  }
  @keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
  }
  @keyframes slideOut {
    from { transform: translateX(0); opacity: 1; }
    to { transform: translateX(100%); opacity: 0; }
  }
`;
document.head.appendChild(style);

console.log('App.js loaded successfully');
