// Login Modal Handler (only on index.php)
const loginModal = document.getElementById("loginModal");
if (loginModal) {
  loginModal.addEventListener("show.bs.modal", (event) => {
    const button = event.relatedTarget;
    const username = button.getAttribute("data-username");

    const modalUsernameInput = loginModal.querySelector("#modalUsername");
    const displayUsername = loginModal.querySelector("#displayUsername");

    modalUsernameInput.value = username;
    displayUsername.textContent = username;
  });
}

function formatRupiah(input) {
  let value = input.value.replace(/[^,\d]/g, "").toString();
  let split = value.split(",");
  let sisa = split[0].length % 3;
  let rupiah = split[0].substr(0, sisa);
  let ribuan = split[0].substr(sisa).match(/\d{3}/gi);

  if (ribuan) {
    let separator = sisa ? "." : "";
    rupiah += separator + ribuan.join(".");
  }

  rupiah = split[1] != undefined ? rupiah + "," + split[1] : rupiah;
  input.value = rupiah;
}

// Quick Amount Buttons
function setAmount(amount) {
  const amountInput = document.querySelector('input[name="amount"]');
  if (amountInput) {
    amountInput.value = formatRupiahString(amount.toString());
    amountInput.focus();
  }
}

// Format number to rupiah string
function formatRupiahString(angka) {
  var number_string = angka.replace(/[^,\d]/g, "").toString(),
    split = number_string.split(","),
    sisa = split[0].length % 3,
    rupiah = split[0].substr(0, sisa),
    ribuan = split[0].substr(sisa).match(/\d{3}/gi);

  if (ribuan) {
    separator = sisa ? "." : "";
    rupiah += separator + ribuan.join(".");
  }

  rupiah = split[1] != undefined ? rupiah + "," + split[1] : rupiah;
  return rupiah;
}

// Auto-clear form after successful submit
window.addEventListener("DOMContentLoaded", function () {
  // Check if there's a success parameter in URL
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get("success") === "1") {
    // Find the main transaction form
    const transactionForm = document.querySelector('form[action*="process"]');
    if (transactionForm) {
      // Reset form but keep default values
      const dateInput = transactionForm.querySelector('input[type="date"]');
      const amountInput = transactionForm.querySelector('input[name="amount"]');
      const descInput = transactionForm.querySelector(
        'input[name="description"]'
      );

      if (amountInput) amountInput.value = "";
      if (descInput) descInput.value = "";
      if (dateInput) dateInput.value = new Date().toISOString().split("T")[0];

      // Focus on description for quick next entry
      if (descInput) descInput.focus();
    }

    // Remove success parameter from URL without reload
    const newUrl =
      window.location.pathname +
      window.location.search.replace(/[?&]success=1/, "").replace(/^&/, "?");
    window.history.replaceState({}, "", newUrl);
  }

  // Highlight newly added transaction (if there's a new_id parameter)
  const newId = urlParams.get("new_id");
  if (newId) {
    setTimeout(() => {
      const newRow = document.querySelector(`tr[data-id="${newId}"]`);
      if (newRow) {
        newRow.style.backgroundColor = "#fff3cd";
        setTimeout(() => {
          newRow.style.transition = "background-color 1s";
          newRow.style.backgroundColor = "";
        }, 2000);
      }
    }, 100);
  }
});

// Loading state for submit buttons
document.addEventListener("DOMContentLoaded", function () {
  const forms = document.querySelectorAll("form");
  forms.forEach((form) => {
    form.addEventListener("submit", function (e) {
      const submitBtn = this.querySelector('button[type="submit"]');
      if (submitBtn && !submitBtn.disabled) {
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML =
          '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';

        // Re-enable after 5 seconds as fallback
        setTimeout(() => {
          submitBtn.disabled = false;
          submitBtn.innerHTML = originalText;
        }, 5000);
      }
    });
  });
});

// Enhanced delete confirmation with transaction details
function confirmDelete(id, description, amount, date) {
  const formattedAmount = new Intl.NumberFormat("id-ID").format(amount);
  const message = `Hapus transaksi ini?\n\nKeterangan: ${description}\nJumlah: Rp ${formattedAmount}\nTanggal: ${date}`;
  return confirm(message);
}

// Change project via POST (for clean URLs)
function changeProject(projectId) {
  const form = document.createElement("form");
  form.method = "POST";
  form.action = "";

  const csrfToken = document.querySelector('input[name="csrf_token"]');
  if (csrfToken) {
    const csrfInput = document.createElement("input");
    csrfInput.type = "hidden";
    csrfInput.name = "csrf_token";
    csrfInput.value = csrfToken.value;
    form.appendChild(csrfInput);
  }

  const updateInput = document.createElement("input");
  updateInput.type = "hidden";
  updateInput.name = "update_filters";
  updateInput.value = "1";
  form.appendChild(updateInput);

  const projectInput = document.createElement("input");
  projectInput.type = "hidden";
  projectInput.name = "project_id";
  projectInput.value = projectId;
  form.appendChild(projectInput);

  document.body.appendChild(form);
  form.submit();
}
