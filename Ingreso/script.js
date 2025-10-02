document.addEventListener('DOMContentLoaded', () => {
  const menuToggle = document.getElementById('menu-toggle');
  const navLinks = document.getElementById('nav-links');
  const contactForm = document.getElementById('contact-form');

  // Toggle menú móvil
  menuToggle.addEventListener('click', () => {
    navLinks.classList.toggle('active');
  });

  // Cerrar menú al dar click en link
  navLinks.querySelectorAll('a').forEach(link => {
    link.addEventListener('click', () => {
      if (navLinks.classList.contains('active')) {
        navLinks.classList.remove('active');
      }
    });
  });

  // Formulario
  contactForm.addEventListener('submit', (event) => {
    event.preventDefault();

    const fullName = document.getElementById('full-name').value;
    const isStudent = document.getElementById('is-student').value === 'si' ? 'Sí' : 'No';
    const courseInterest = document.getElementById('course-interest').options[document.getElementById('course-interest').selectedIndex].text;
    const email = document.getElementById('email').value;
    const phone = document.getElementById('phone').value;

    const emailBody = `Hola,%0A%0AMe gustaría obtener más información sobre los cursos.%0A%0ANombre: ${fullName}%0AEstudiante: ${isStudent}%0ACurso de interés: ${courseInterest}%0ACorreo: ${email}%0ACelular: ${phone}%0A%0AGracias.`;

    const yourEmail = "your-email@gmail.com"; // 🔴 cámbialo a tu correo real
    const mailtoLink = `mailto:${yourEmail}?subject=Formulario de Contacto L¥ØNS&body=${emailBody}`;

    window.location.href = mailtoLink;
  });
});
