(function () {
  if (getToken() && getUser()) {
    redirectToDashboard(getUser().role);
    return;
  }

  const form = document.getElementById('loginForm');
  const emailInput = document.getElementById('loginEmail');
  const passwordInput = document.getElementById('loginPassword');
  const emailError = document.getElementById('emailError');
  const passwordError = document.getElementById('passwordError');
  const messageEl = document.getElementById('loginMessage');
  const submitBtn = document.getElementById('loginSubmitBtn');
  const rememberMe = document.getElementById('rememberMe');
  const REMEMBER_KEY = 'epa_remembered_email';

  const remembered = localStorage.getItem(REMEMBER_KEY);
  if (remembered) {
    emailInput.value = remembered;
    rememberMe.checked = true;
  }

  function setMessage(text, type) {
    messageEl.textContent = text;
    messageEl.className = 'login-message' + (type ? ' ' + type : '');
  }

  // Show/hide password
  const togglePasswordBtn = document.getElementById('togglePassword');
  togglePasswordBtn.addEventListener('click', () => {
    const isHidden = passwordInput.type === 'password';
    passwordInput.type = isHidden ? 'text' : 'password';
    togglePasswordBtn.textContent = isHidden ? 'Hide' : 'Show';
    togglePasswordBtn.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
  });

  // Demo accounts accordion
  const demoToggleBtn = document.getElementById('toggleDemoBtn');
  const demoAccounts = document.getElementById('demoAccounts');
  demoToggleBtn.addEventListener('click', () => {
    const willShow = demoAccounts.classList.contains('hidden');
    demoAccounts.classList.toggle('hidden', !willShow);
    demoToggleBtn.setAttribute('aria-expanded', String(willShow));
    demoToggleBtn.textContent = willShow ? 'Hide Demo Accounts' : 'View Demo Accounts';
  });

  // Fill form from a demo account, but still require the user to click Login themselves
  document.querySelectorAll('.use-demo-btn').forEach((btn) => {
    btn.addEventListener('click', () => {
      const row = btn.closest('.demo-account');
      emailInput.value = row.dataset.email;
      passwordInput.value = 'password123';
      emailError.textContent = '';
      passwordError.textContent = '';
      setMessage('', '');
      emailInput.focus();
    });
  });

  function validate(email, password) {
    let valid = true;
    emailError.textContent = '';
    passwordError.textContent = '';
    if (!email) {
      emailError.textContent = 'Email is required.';
      valid = false;
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      emailError.textContent = 'Please enter a valid email address.';
      valid = false;
    }
    if (!password) {
      passwordError.textContent = 'Password is required.';
      valid = false;
    }
    return valid;
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const email = emailInput.value.trim();
    const password = passwordInput.value;
    setMessage('', '');
    if (!validate(email, password)) return;

    submitBtn.disabled = true;
    const originalText = submitBtn.textContent;
    submitBtn.textContent = 'Signing in...';

    try {
      const data = await api('/auth/login', { method: 'POST', body: { email, password } });
      if (rememberMe.checked) {
        localStorage.setItem(REMEMBER_KEY, email);
      } else {
        localStorage.removeItem(REMEMBER_KEY);
      }
      setSession(data.token, data.user);
      setMessage(`Sign-in successful. Redirecting to your ${data.user.role} dashboard...`, 'success');
      setTimeout(() => redirectToDashboard(data.user.role), 500);
    } catch (err) {
      setMessage(err.message || 'Sign-in failed. Please check your email and password.', 'error');
      submitBtn.disabled = false;
      submitBtn.textContent = originalText;
    }
  });
})();
