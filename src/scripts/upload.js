document.addEventListener("DOMContentLoaded", function () {
    let fileInput = document.getElementById("photo");
    let fileNameDisplay = document.getElementById("file-name");

    if (fileInput) {
        fileInput.addEventListener("change", function () {
            let fileName = fileInput.files.length > 0 ? fileInput.files[0].name : "No file selected";
            fileNameDisplay.textContent = fileName;
        });
    }
});
