const canvas = document.getElementById('particles');
const ctx = canvas.getContext('2d');

canvas.width = window.innerWidth;
canvas.height = window.innerHeight;

window.addEventListener('resize', () => {
  canvas.width = window.innerWidth;
  canvas.height = window.innerHeight;
});

const objectTypes = ['book', 'pencil', 'notebook', 'globe'];

class Particle {
  constructor() {
    this.type = objectTypes[Math.floor(Math.random()*objectTypes.length)];
    this.x = Math.random()*canvas.width;
    this.y = Math.random()*canvas.height;
    this.size = Math.random()*20 + 15;
    this.speedX = Math.random()*0.5 - 0.25;
    this.speedY = Math.random()*0.5 - 0.25;
    this.angle = Math.random()*2*Math.PI;
    this.color = `rgba(255,255,255,${Math.random()*0.5 + 0.3})`;
  }
  update() {
    this.x += this.speedX;
    this.y += this.speedY;
    this.angle += 0.01;
    if(this.x < -50) this.x = canvas.width + 50;
    if(this.x > canvas.width + 50) this.x = -50;
    if(this.y < -50) this.y = canvas.height + 50;
    if(this.y > canvas.height + 50) this.y = -50;
  }
  draw() {
    ctx.save();
    ctx.translate(this.x, this.y);
    ctx.rotate(this.angle);
    switch(this.type) {
      case 'book':
        ctx.fillStyle = this.color;
        ctx.fillRect(-this.size/2, -this.size/4, this.size, this.size/2);
        break;
      case 'pencil':
        ctx.fillStyle = 'yellow';
        ctx.fillRect(-this.size/8, -this.size/2, this.size/4, this.size);
        ctx.fillStyle = 'gray';
        ctx.fillRect(-this.size/8, -this.size/2, this.size/4, this.size*0.2);
        break;
      case 'notebook':
        ctx.fillStyle = '#ff8c00';
        ctx.fillRect(-this.size/2, -this.size/2, this.size, this.size);
        ctx.strokeStyle = 'white';
        ctx.strokeRect(-this.size/2, -this.size/2, this.size, this.size);
        break;
      case 'globe':
        ctx.fillStyle = '#00c6ff';
        ctx.beginPath();
        ctx.arc(0,0,this.size/2,0,Math.PI*2);
        ctx.fill();
        ctx.strokeStyle='white';
        ctx.stroke();
        break;
    }
    ctx.restore();
  }
}

const particles = [];
for(let i=0;i<100;i++) particles.push(new Particle());

function animate() {
  ctx.clearRect(0,0,canvas.width,canvas.height);
  particles.forEach(p=>{p.update(); p.draw();});
  requestAnimationFrame(animate);
}
animate();
