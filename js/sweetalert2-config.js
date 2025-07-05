// Configure SweetAlert2 to behave more like Alertify
Swal.mixin({
    toast: true,
    position: 'top-right',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer)
        toast.addEventListener('mouseleave', Swal.resumeTimer)
    }
});

// Custom functions to mimic Alertify behavior
window.alertify = {
    success: function(message) {
        console.log('alertify.success called with:', message);
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            html: message,
            toast: true,
            position: 'top-right',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            background: '#d4edda',
            color: '#155724',
            iconColor: '#28a745'
        });
    },
    error: function(message) {
        console.log('alertify.error called with:', message);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            html: message,
            toast: true,
            position: 'top-right',
            showConfirmButton: false,
            timer: 4000,
            timerProgressBar: true,
            background: '#f8d7da',
            color: '#721c24',
            iconColor: '#dc3545'
        });
    },
    confirm: function(message, callback) {
        console.log('alertify.confirm called with:', message);
        Swal.fire({
            title: 'Confirm',
            html: message,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'OK',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            background: '#fff',
            color: '#333'
        }).then((result) => {
            if (result.isConfirmed) {
                callback(true);
            } else {
                callback(false);
            }
        });
    }
};

// Ensure alertify is available globally
console.log('alertify object defined:', typeof window.alertify); 