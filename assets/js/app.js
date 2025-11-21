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
