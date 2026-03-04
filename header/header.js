document.addEventListener("DOMContentLoaded", function () {

  const profileBox = document.getElementById("profileBox");
  const dropdown = document.getElementById("profileDropdown");

  if (profileBox) {
    profileBox.addEventListener("click", function (e) {
      e.stopPropagation();
      dropdown.classList.toggle("show");
    });

    document.addEventListener("click", function () {
      dropdown.classList.remove("show");
    });
  }

});