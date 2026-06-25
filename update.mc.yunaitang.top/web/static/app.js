/**
 * MC Launcher Update Server — Admin Web UI JavaScript
 * Light weight, no framework, ~80 lines
 */

document.addEventListener("DOMContentLoaded", function () {
    // ── Grayscale toggle ──────────────────────────────────
    var grayscaleToggle = document.getElementById("grayscale-toggle");
    var grayscalePctGroup = document.getElementById("grayscale-pct-group");
    var grayscalePctInput = document.getElementById("f-grayscale-pct");
    var grayscalePctLabel = document.getElementById("grayscale-pct-label");

    if (grayscaleToggle && grayscalePctGroup) {
        grayscaleToggle.addEventListener("change", function () {
            if (grayscaleToggle.checked) {
                grayscalePctGroup.style.display = "block";
            } else {
                grayscalePctGroup.style.display = "none";
            }
        });
    }

    // ── Range slider live value ───────────────────────────
    if (grayscalePctInput && grayscalePctLabel) {
        grayscalePctInput.addEventListener("input", function () {
            grayscalePctLabel.textContent = grayscalePctInput.value + "%";
        });
    }

    // ── Auto-dismiss alerts ──────────────────────────────
    var alerts = document.querySelectorAll(".alert");
    alerts.forEach(function (alert) {
        setTimeout(function () {
            alert.style.transition = "opacity 0.5s";
            alert.style.opacity = "0";
            setTimeout(function () {
                if (alert.parentNode) {
                    alert.parentNode.removeChild(alert);
                }
            }, 500);
        }, 4000);
    });

    // ── Empty string values: clear placeholder values on submit ──
    var forms = document.querySelectorAll("form");
    forms.forEach(function (form) {
        form.addEventListener("submit", function () {
            var optionalInputs = form.querySelectorAll(
                'input:not([required]), select:not([required])'
            );
            optionalInputs.forEach(function (input) {
                // For URL inputs, clear invalid/empty values to avoid 422 errors
                if (input.type === "url" && input.value === "") {
                    input.disabled = true;
                }
                // For number inputs, clear if empty string
                if (input.type === "number" && input.value === "") {
                    input.disabled = true;
                }
            });
        });
    });
});
