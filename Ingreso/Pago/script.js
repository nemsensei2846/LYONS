document.addEventListener('DOMContentLoaded', () => {
  const selectButtons = document.querySelectorAll('.select-btn');
  const totalPriceEl = document.getElementById('total-price');
  const buyBtn = document.getElementById('buy-btn');
  const modal = document.getElementById('payment-modal');
  const closeBtn = document.getElementById('close-modal-btn');
  const whatsappLink = document.getElementById('whatsapp-link');
  const copyBtnPichincha = document.getElementById('copy-btn-pichincha');
  const copyBtnPacifico = document.getElementById('copy-btn-pacifico');
  const accountPichinchaEl = document.getElementById('account-pichincha');
  const accountPacificoEl = document.getElementById('account-pacifico');

  const coursePrice = 10;
  const whatsappNumber = "593999456415"; 
  const selectedCourses = new Set();

  function updateTotalPrice() {
    const total = selectedCourses.size * coursePrice;
    totalPriceEl.textContent = `$${total.toFixed(2)}`;
    buyBtn.disabled = selectedCourses.size === 0;
    buyBtn.classList.toggle('opacity-50', selectedCourses.size === 0);
  }

  selectButtons.forEach(button => {
    button.addEventListener('click', () => {
      const courseValue = button.dataset.course;
      if (selectedCourses.has(courseValue)) {
        selectedCourses.delete(courseValue);
        button.textContent = 'Seleccionar';
        button.classList.remove('bg-green-600', 'hover:bg-green-500');
        button.classList.add('btn-gold');
      } else {
        selectedCourses.add(courseValue);
        button.textContent = 'Seleccionado';
        button.classList.remove('btn-gold');
        button.classList.add('bg-green-600', 'hover:bg-green-500');
      }
      updateTotalPrice();
    });
  });

  buyBtn.addEventListener('click', () => {
    if (selectedCourses.size > 0) {
      const total = parseFloat(totalPriceEl.textContent.replace('$', ''));
      const selectedCourseNames = Array.from(selectedCourses).map(value => {
        const card = document.querySelector(`[data-course="${value}"]`).closest('.card-course');
        return card.querySelector('h3').textContent;
      });

      const message = `Hola L¥ØNS, acabo de realizar la compra de los siguientes cursos: ${selectedCourseNames.join(', ')}. El total es $${total.toFixed(2)}. Adjunto el comprobante.`;
      const encodedMessage = encodeURIComponent(message);
      whatsappLink.href = `https://wa.me/${whatsappNumber}?text=${encodedMessage}`;

      modal.classList.remove('hidden');
      modal.classList.add('flex');
      document.body.style.overflow = 'hidden';
    }
  });

  closeBtn.addEventListener('click', () => {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.style.overflow = 'auto';
  });

  function copyToClipboard(text, button) {
    navigator.clipboard.writeText(text).then(() => {
      button.textContent = '¡Copiado!';
      setTimeout(() => {
        button.textContent = 'Copiar';
      }, 2000);
    });
  }

  copyBtnPichincha.addEventListener('click', () => {
    copyToClipboard(accountPichinchaEl.textContent, copyBtnPichincha);
  });

  copyBtnPacifico.addEventListener('click', () => {
    copyToClipboard(accountPacificoEl.textContent, copyBtnPacifico);
  });

  updateTotalPrice();
});
