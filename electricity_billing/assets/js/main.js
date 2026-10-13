document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll("[data-password-toggle]").forEach((button) => {
        button.addEventListener("click", () => {
            const input = document.getElementById("password");
            const visible = input.type === "password";
            input.type = visible ? "text" : "password";
            button.textContent = visible ? "Hide" : "Show";
            button.setAttribute("aria-label", visible ? "Hide password" : "Show password");
        });
    });

    document.querySelectorAll("form[data-confirm]").forEach((form) => {
        form.addEventListener("submit", (event) => {
            if (!window.confirm(form.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });

    const billSelect = document.querySelector("[data-bill-select]");
    const paymentAmount = document.querySelector("[data-payment-amount]");
    const balanceHint = document.querySelector("[data-balance-hint]");
    if (billSelect && paymentAmount && balanceHint) {
        const updateBalance = () => {
            const option = billSelect.options[billSelect.selectedIndex];
            const balance = option.dataset.balance;
            if (!balance) {
                paymentAmount.removeAttribute("max");
                balanceHint.textContent = "Select a bill to see the remaining balance.";
                return;
            }
            paymentAmount.max = balance;
            paymentAmount.value = balance;
            balanceHint.textContent = `Remaining balance: Rs. ${Number(balance).toFixed(2)}`;
        };
        billSelect.addEventListener("change", updateBalance);
    }
});
