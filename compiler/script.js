function runCode() {
  fetch("run_code.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
   body: new URLSearchParams({
  code: document.getElementById("code-editor").value,
  question_id: new URLSearchParams(window.location.search).get("id")
})

  })
  .then(res => res.json())
  .then(data => {
    const output = document.getElementById("execution-output");
    const tbody = document.querySelector("#testcase-table tbody");
    tbody.innerHTML = "";

    if (data.status === "compile_error") {
      output.textContent = data.message;
      return;
    }

    output.textContent =
      data.status === "success"
        ? "All testcases passed successfully."
        : "Some testcases failed.";

    data.results.forEach(r => {
      tbody.innerHTML += `
        <tr>
          <td>${r.expected}</td>
          <td>${r.output}</td>
          <td>${r.status}</td>
        </tr>
      `;
    });
  });
}
