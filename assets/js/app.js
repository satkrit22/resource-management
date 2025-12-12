/**
 * Resource Management System - Main JavaScript
 */

// Toggle sidebar on mobile
function toggleSidebar() {
  const sidebar = document.getElementById("sidebar")
  sidebar.classList.toggle("active")
}

// Close sidebar when clicking outside on mobile
document.addEventListener("click", (e) => {
  const sidebar = document.getElementById("sidebar")
  const menuToggle = document.querySelector(".menu-toggle")

  if (sidebar && sidebar.classList.contains("active")) {
    if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
      sidebar.classList.remove("active")
    }
  }
})

// Dropdown toggle
function toggleDropdown(id) {
  const dropdown = document.getElementById(id)

  // Close other dropdowns
  document.querySelectorAll(".dropdown.active").forEach((d) => {
    if (d.id !== id) d.classList.remove("active")
  })

  dropdown.classList.toggle("active")
}

// Close dropdowns when clicking outside
document.addEventListener("click", (e) => {
  if (!e.target.closest(".dropdown")) {
    document.querySelectorAll(".dropdown.active").forEach((d) => {
      d.classList.remove("active")
    })
  }
})

// Modal functions
function openModal(id) {
  const modal = document.getElementById(id)
  if (modal) {
    modal.classList.add("active")
    document.body.style.overflow = "hidden"
  }
}

function closeModal(id) {
  const modal = document.getElementById(id)
  if (modal) {
    modal.classList.remove("active")
    document.body.style.overflow = ""
  }
}

// Close modal on overlay click
document.querySelectorAll(".modal-overlay").forEach((overlay) => {
  overlay.addEventListener("click", function (e) {
    if (e.target === this) {
      this.classList.remove("active")
      document.body.style.overflow = ""
    }
  })
})

// Close modal on Escape key
document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") {
    document.querySelectorAll(".modal-overlay.active").forEach((modal) => {
      modal.classList.remove("active")
    })
    document.body.style.overflow = ""
  }
})

// Toast notifications
function showToast(message, type = "info") {
  // Remove existing toasts
  document.querySelectorAll(".toast").forEach((t) => t.remove())

  const toast = document.createElement("div")
  toast.className = `toast toast-${type}`
  toast.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        padding: 1rem 1.5rem;
        background: ${type === "success" ? "#10b981" : type === "error" ? "#ef4444" : "#3b82f6"};
        color: white;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 9999;
        animation: slideIn 0.3s ease;
    `
  toast.textContent = message

  document.body.appendChild(toast)

  setTimeout(() => {
    toast.style.animation = "slideOut 0.3s ease"
    setTimeout(() => toast.remove(), 300)
  }, 3000)
}

// Add toast animations
const style = document.createElement("style")
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`
document.head.appendChild(style)

// Mark all notifications as read
function markAllRead() {
  fetch("api/notifications.php", {
    method: "PUT",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({}),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        document.querySelectorAll(".notification-item.unread").forEach((item) => {
          item.classList.remove("unread")
        })
        const dot = document.querySelector(".notification-dot")
        if (dot) dot.remove()
      }
    })
}

// Confirm delete
function confirmDelete(message, callback) {
  if (confirm(message || "Are you sure you want to delete this item?")) {
    callback()
  }
}

// Format date
function formatDate(dateString) {
  const options = { year: "numeric", month: "short", day: "numeric" }
  return new Date(dateString).toLocaleDateString("en-US", options)
}

// Format datetime
function formatDateTime(dateString) {
  const options = { year: "numeric", month: "short", day: "numeric", hour: "numeric", minute: "2-digit" }
  return new Date(dateString).toLocaleDateString("en-US", options)
}

// Debounce function
function debounce(func, wait) {
  let timeout
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout)
      func(...args)
    }
    clearTimeout(timeout)
    timeout = setTimeout(later, wait)
  }
}

// Initialize tabs
document.querySelectorAll(".tab-btn").forEach((btn) => {
  btn.addEventListener("click", function () {
    const tabGroup = this.closest(".tabs").parentElement

    // Remove active from all tabs
    tabGroup.querySelectorAll(".tab-btn").forEach((t) => t.classList.remove("active"))
    tabGroup.querySelectorAll(".tab-content").forEach((c) => c.classList.remove("active"))

    // Add active to clicked tab
    this.classList.add("active")
    const target = tabGroup.querySelector(this.dataset.target)
    if (target) target.classList.add("active")
  })
})

// Form validation
function validateForm(form) {
  let isValid = true

  form.querySelectorAll("[required]").forEach((field) => {
    if (!field.value.trim()) {
      field.classList.add("error")
      isValid = false
    } else {
      field.classList.remove("error")
    }
  })

  return isValid
}

// Auto-hide alerts after 5 seconds
document.querySelectorAll(".alert").forEach((alert) => {
  setTimeout(() => {
    alert.style.animation = "slideOut 0.3s ease"
    setTimeout(() => alert.remove(), 300)
  }, 5000)
})

console.log("Resource Management System initialized")
