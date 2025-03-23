gsap.registerPlugin(ScrollTrigger);

// Hiệu ứng cho header khi load trang
gsap.from("header", { duration: 1, y: -50, opacity: 0, ease: "power2.out" });

// Hiệu ứng cho tiêu đề của mỗi section khi cuộn
gsap.utils.toArray("section").forEach(section => {
  gsap.from(section.querySelector("h2"), {
    scrollTrigger: {
      trigger: section,
      start: "top 80%",
    },
    duration: 1,
    y: 50,
    opacity: 0,
    ease: "power2.out"
  });
});

// Animation cho slider courses-container
gsap.to(".courses-container", {
  x: "-=1000", // Điều chỉnh giá trị này phù hợp với chiều rộng thực tế của container
  duration: 20,
  ease: "none",
  repeat: -1,
  modifiers: {
    x: gsap.utils.unitize(x => parseFloat(x) % 1000) // Tạo hiệu ứng lặp lại mượt mà
  }
});

// Hiệu ứng cho các card khoá học khi cuộn
gsap.from(".course-card", {
  scrollTrigger: {
    trigger: ".courses-container",
    start: "top 80%",
  },
  duration: 1,
  y: 50,
  opacity: 0,
  stagger: 0.2,
  ease: "power2.out"
});

// Micro interactions cho course-card khi rê chuột
document.querySelectorAll('.course-card').forEach(card => {
  card.addEventListener('mouseenter', () => {
    gsap.to(card, { scale: 1.05, duration: 0.3, ease: "power1.out" });
  });
  card.addEventListener('mouseleave', () => {
    gsap.to(card, { scale: 1, duration: 0.3, ease: "power1.out" });
  });
});

// Micro interactions cho blog-post
document.querySelectorAll('.blog-post').forEach(post => {
  post.addEventListener('mouseenter', () => {
    gsap.to(post, { scale: 1.03, duration: 0.3, ease: "power1.out" });
  });
  post.addEventListener('mouseleave', () => {
    gsap.to(post, { scale: 1, duration: 0.3, ease: "power1.out" });
  });
});

// Micro interactions cho testimonial
document.querySelectorAll('.testimonial').forEach(testimonial => {
  testimonial.addEventListener('mouseenter', () => {
    gsap.to(testimonial, { scale: 1.02, duration: 0.3, ease: "power1.out" });
  });
  testimonial.addEventListener('mouseleave', () => {
    gsap.to(testimonial, { scale: 1, duration: 0.3, ease: "power1.out" });
  });
});

// Micro interactions cho FAQ: Toggle hiển thị câu trả lời khi nhấp vào tiêu đề
document.querySelectorAll('.faq-item h4').forEach(faqHeader => {
  faqHeader.style.cursor = "pointer";
  faqHeader.addEventListener("click", () => {
    const faqItem = faqHeader.parentElement;
    const answer = faqItem.querySelector("p");
    if (faqItem.classList.contains("active")) {
      // Thu gọn câu trả lời
      gsap.to(answer, { height: 0, opacity: 0, duration: 0.3, ease: "power2.out" });
      faqItem.classList.remove("active");
    } else {
      // Mở rộng: Lấy chiều cao tự nhiên của phần trả lời
      answer.style.height = "auto";
      const autoHeight = answer.clientHeight;
      gsap.fromTo(answer, { height: 0, opacity: 0 }, { height: autoHeight, opacity: 1, duration: 0.3, ease: "power2.out" });
      faqItem.classList.add("active");
    }
  });
});

// ----------------------
// Use global THREE objects (loaded via script tags in the HTML)
class ModelViewer {
  constructor() {
    // Check if THREE.js is loaded
    if (typeof THREE === 'undefined') {
      console.error('THREE.js not loaded. Please include it in your HTML.');
      return;
    }
    
    this.scene = new THREE.Scene();
    this.camera = new THREE.PerspectiveCamera(
      45, 
      window.innerWidth / window.innerHeight, 
      0.1, 
      1000
    );
    this.camera.position.set(0, 2, 8);

    this.renderer = new THREE.WebGLRenderer({
      canvas: document.getElementById('robot'),
      antialias: true,
      alpha: true
    });
    this.renderer.setSize(window.innerWidth, window.innerHeight);
    this.renderer.setPixelRatio(window.devicePixelRatio);

    this.controls = new OrbitControls(this.camera, this.renderer.domElement);
    this.controls.enableDamping = true;
    this.controls.dampingFactor = 0.05;
    this.controls.maxDistance = 15;
    this.controls.minDistance = 3;

    // **Clock & Mixer** để chạy animation
    this.clock = new THREE.Clock();
    this.mixer = null;

    this.setupLighting();
    this.loadModel();
    this.animate();

    window.addEventListener('resize', () => this.onWindowResize());
  }

  setupLighting() {
    const ambientLight = new THREE.AmbientLight(0xffffff, 1.2);
    this.scene.add(ambientLight);

    const directionalLight = new THREE.DirectionalLight(0xffffff, 1);
    directionalLight.position.set(5, 5, 5);
    this.scene.add(directionalLight);
  }

  loadModel() {
    const loader = new GLTFLoader();
    loader.load(window.BASE_URL +'app/views/uploads/robot.glb', (gltf) => {
      const model = gltf.scene;
      
      // Canh giữa model
      const box = new THREE.Box3().setFromObject(model);
      const center = box.getCenter(new THREE.Vector3());
      model.position.sub(center);

      model.position.x += 4;
      model.position.y -= 1;

      // Scale model
      const size = box.getSize(new THREE.Vector3());
      const maxDim = Math.max(size.x, size.y, size.z);
      const scale = 5.5 / maxDim;
      model.scale.multiplyScalar(scale);

      // Thêm model vào scene
      this.scene.add(model);

      // **Quan trọng**: kiểm tra gltf.animations
      console.log('Số lượng clip animation:', gltf.animations.length);
      console.log('Danh sách clip:', gltf.animations);

      // Nếu file có chứa animation
      if (gltf.animations && gltf.animations.length > 0) {
        this.mixer = new THREE.AnimationMixer(model);

        // Cách 1: Phát tất cả clip (nếu muốn chạy cùng lúc)
        gltf.animations.forEach((clip) => {
          const action = this.mixer.clipAction(clip);
          action.play();
        });

        // Cách 2 (tùy chọn): Nếu muốn chỉ phát 1 clip
        // const clip = gltf.animations[0];
        // const action = this.mixer.clipAction(clip);
        // action.play();
      }
    }, undefined, (error) => {
      console.error('Error loading model:', error);
    });
  }

  animate() {
    requestAnimationFrame(() => this.animate());

    // **Cập nhật mixer** mỗi frame để hoạt ảnh chạy
    if (this.mixer) {
      const delta = this.clock.getDelta();
      this.mixer.update(delta);
    }

    this.controls.update();
    this.renderer.render(this.scene, this.camera);
  }

  onWindowResize() {
    this.camera.aspect = window.innerWidth / window.innerHeight;
    this.camera.updateProjectionMatrix();
    this.renderer.setSize(window.innerWidth, window.innerHeight);
  }
}

document.addEventListener('DOMContentLoaded', () => {
  new ModelViewer();
});



// ----------------------
// Micro interactions cho các liên kết trong header (đã gộp lại thành 1 block duy nhất)
document.querySelectorAll('header nav ul li a').forEach(link => {
  link.addEventListener('mouseenter', () => {
    gsap.to(link, { scale: 1.1, duration: 0.2, ease: "power1.out" });
  });
  link.addEventListener('mouseleave', () => {
    gsap.to(link, { scale: 1, duration: 0.2, ease: "power1.out" });
  });
});

/* Micro interactions cho submenu trong mục Khoá Học */
document.querySelectorAll('.has-submenu').forEach(item => {
  item.addEventListener('mouseenter', () => {
    const submenu = item.querySelector('.submenu');
    gsap.to(submenu, { opacity: 1, duration: 0.3, ease: "power2.out", onStart: () => {
      submenu.style.visibility = "visible";
    } });
  });
  item.addEventListener('mouseleave', () => {
    const submenu = item.querySelector('.submenu');
    gsap.to(submenu, { opacity: 0, duration: 0.3, ease: "power2.out", onComplete: () => {
      submenu.style.visibility = "hidden";
    } });
  });
});

window.addEventListener("scroll", function() {
  const header = document.querySelector("header");
  if (window.scrollY > 50) {
    header.classList.add("scrolled");
  } else {
    header.classList.remove("scrolled");
  }
});

document.addEventListener('DOMContentLoaded', function() {
    // Check if GSAP is loaded
    if (typeof gsap === 'undefined') {
        console.warn('GSAP not loaded. Some animations will not work.');
        return;
    }

    // Register plugins if available
    if (typeof ScrollTrigger !== 'undefined') {
        gsap.registerPlugin(ScrollTrigger);
    }

    // Smooth header transition on scroll
    const header = document.querySelector('header');
    
    window.addEventListener('scroll', () => {
        if (window.scrollY > 50) {
            header.classList.add('scrolled');
        } else {
            header.classList.remove('scrolled');
        }
    });

    // Hero animations
    const heroContent = document.querySelector('.hero-content');
    if (heroContent) {
        const heroTl = gsap.timeline();
        heroTl.from('.hero-content h1', { 
            opacity: 0, 
            y: 30, 
            duration: 1, 
            ease: 'power3.out' 
        })
        .from('.hero-content p', {
            opacity: 0,
            y: 30,
            duration: 1,
            ease: 'power3.out'
        }, '-=0.6')
        .from('.hero-content .btn', {
            opacity: 0,
            y: 30,
            duration: 1,
            ease: 'power3.out'
        }, '-=0.6');
    }

    // Scroll animations for each section
    if (typeof ScrollTrigger !== 'undefined') {
        // Section titles
        gsap.utils.toArray('section h2').forEach(title => {
            gsap.from(title, {
                scrollTrigger: {
                    trigger: title,
                    start: 'top 80%',
                },
                duration: 1,
                y: 30,
                opacity: 0,
                ease: 'power3.out'
            });
        });

        // Feature cards animations
        ScrollTrigger.batch('.feature-card', {
            interval: 0.1,
            batchMax: 4,
            onEnter: batch => gsap.from(batch, {
                autoAlpha: 0,
                y: 50,
                stagger: 0.15,
                duration: 0.8,
                ease: 'power3.out'
            })
        });
        
        // AI Path steps animation
        ScrollTrigger.batch('.path-step', {
            interval: 0.1,
            batchMax: 3,
            onEnter: batch => gsap.from(batch, {
                autoAlpha: 0,
                x: -50,
                stagger: 0.2,
                duration: 0.8,
                ease: 'back.out(1.7)'
            })
        });
        
        // Course cards animation
        ScrollTrigger.batch('.course-card', {
            interval: 0.1,
            batchMax: 3,
            onEnter: batch => gsap.from(batch, {
                autoAlpha: 0,
                y: 70,
                stagger: 0.2,
                duration: 0.8,
                ease: 'power3.out'
            })
        });
        
        // Testimonials animation
        ScrollTrigger.batch('.testimonial', {
            interval: 0.1,
            batchMax: 3,
            onEnter: batch => gsap.from(batch, {
                autoAlpha: 0,
                scale: 0.9,
                stagger: 0.2,
                duration: 0.8,
                ease: 'power3.out'
            })
        });
        
        // Blog cards animation
        ScrollTrigger.batch('.blog-card', {
            interval: 0.1,
            batchMax: 3,
            onEnter: batch => gsap.from(batch, {
                autoAlpha: 0,
                y: 50,
                stagger: 0.2,
                duration: 0.8,
                ease: 'power3.out'
            })
        });
    }
    
    // Micro interactions
    setupMicroInteractions();
    
    // FAQ Accordion
    setupFaqAccordion();
    
    // Try to initialize 3D model if present
    try {
        if (document.getElementById('robot')) {
            initRobotModel();
        }
    } catch (e) {
        console.warn('Robot model failed to initialize:', e);
    }
});

// Setup micro interactions for various elements
function setupMicroInteractions() {
    // Course cards hover effect
    document.querySelectorAll('.course-card').forEach(card => {
        card.addEventListener('mouseenter', () => {
            gsap.to(card, { scale: 1.03, duration: 0.3, ease: "power1.out" });
        });
        card.addEventListener('mouseleave', () => {
            gsap.to(card, { scale: 1, duration: 0.3, ease: "power1.out" });
        });
    });
    
    // Blog cards hover effect
    document.querySelectorAll('.blog-card').forEach(card => {
        card.addEventListener('mouseenter', () => {
            gsap.to(card, { scale: 1.03, duration: 0.3, ease: "power1.out" });
        });
        card.addEventListener('mouseleave', () => {
            gsap.to(card, { scale: 1, duration: 0.3, ease: "power1.out" });
        });
    });
    
    // Testimonial cards hover effect
    document.querySelectorAll('.testimonial').forEach(testimonial => {
        testimonial.addEventListener('mouseenter', () => {
            gsap.to(testimonial, { scale: 1.02, duration: 0.3, ease: "power1.out" });
        });
        testimonial.addEventListener('mouseleave', () => {
            gsap.to(testimonial, { scale: 1, duration: 0.3, ease: "power1.out" });
        });
    });
    
    // Header link animations
    document.querySelectorAll('header nav ul li a').forEach(link => {
        link.addEventListener('mouseenter', () => {
            gsap.to(link, { y: -2, duration: 0.2, ease: "power1.out" });
        });
        link.addEventListener('mouseleave', () => {
            gsap.to(link, { y: 0, duration: 0.2, ease: "power1.out" });
        });
    });
    
    // Submenu animations
    document.querySelectorAll('.has-submenu').forEach(item => {
        item.addEventListener('mouseenter', () => {
            const submenu = item.querySelector('.submenu');
            if (submenu) {
                gsap.to(submenu, { 
                    opacity: 1, 
                    y: 0, 
                    duration: 0.3, 
                    ease: "power2.out", 
                    onStart: () => {
                        submenu.style.visibility = "visible";
                    } 
                });
            }
        });
        item.addEventListener('mouseleave', () => {
            const submenu = item.querySelector('.submenu');
            if (submenu) {
                gsap.to(submenu, { 
                    opacity: 0, 
                    y: 10, 
                    duration: 0.3, 
                    ease: "power2.out", 
                    onComplete: () => {
                        submenu.style.visibility = "hidden";
                    } 
                });
            }
        });
    });
}

// Setup FAQ accordion functionality
function setupFaqAccordion() {
    const faqItems = document.querySelectorAll('.faq-item');
    
    faqItems.forEach(item => {
        const question = item.querySelector('.faq-question');
        
        question.addEventListener('click', () => {
            // Check if this item is already active
            const isActive = item.classList.contains('active');
            
            // Close all accordion items
            faqItems.forEach(faq => {
                const answer = faq.querySelector('.faq-answer');
                
                // If using GSAP animations
                if (typeof gsap !== 'undefined') {
                    if (faq !== item || isActive) {
                        gsap.to(answer, {
                            height: 0,
                            opacity: 0,
                            duration: 0.3,
                            ease: "power2.out",
                            onComplete: () => {
                                faq.classList.remove('active');
                            }
                        });
                    }
                } else {
                    // Fallback without GSAP
                    faq.classList.remove('active');
                }
            });
            
            // If the clicked item wasn't active before, open it
            if (!isActive) {
                const answer = item.querySelector('.faq-answer');
                
                // If using GSAP animations
                if (typeof gsap !== 'undefined') {
                    // Get natural height
                    gsap.set(answer, { height: "auto", opacity: 1 });
                    const height = answer.offsetHeight;
                    
                    // Animate from 0 to natural height
                    gsap.fromTo(answer, 
                        { height: 0, opacity: 0 }, 
                        { 
                            height: height, 
                            opacity: 1, 
                            duration: 0.5, 
                            ease: "power2.out",
                            onStart: () => {
                                item.classList.add('active');
                            }
                        }
                    );
                } else {
                    // Fallback without GSAP
                    item.classList.add('active');
                }
            }
        });
    });
}

// Initialize Robot 3D Model
function initRobotModel() {
    if (typeof THREE === 'undefined' || typeof GLTFLoader === 'undefined' || typeof OrbitControls === 'undefined') {
        console.warn('THREE.js libraries not loaded. Robot model will not be displayed.');
        return;
    }
    
    class ModelViewer {
        constructor() {
            this.scene = new THREE.Scene();
            this.camera = new THREE.PerspectiveCamera(
                45, 
                window.innerWidth / window.innerHeight, 
                0.1, 
                1000
            );
            this.camera.position.set(0, 2, 8);

            this.renderer = new THREE.WebGLRenderer({
                canvas: document.getElementById('robot'),
                antialias: true,
                alpha: true
            });
            this.renderer.setSize(window.innerWidth, window.innerHeight);
            this.renderer.setPixelRatio(window.devicePixelRatio);

            this.controls = new OrbitControls(this.camera, this.renderer.domElement);
            this.controls.enableDamping = true;
            this.controls.dampingFactor = 0.05;
            this.controls.maxDistance = 15;
            this.controls.minDistance = 3;

            this.clock = new THREE.Clock();
            this.mixer = null;

            this.setupLighting();
            this.loadModel();
            this.animate();

            window.addEventListener('resize', () => this.onWindowResize());
        }

        setupLighting() {
            const ambientLight = new THREE.AmbientLight(0xffffff, 1.2);
            this.scene.add(ambientLight);

            const directionalLight = new THREE.DirectionalLight(0xffffff, 1);
            directionalLight.position.set(5, 5, 5);
            this.scene.add(directionalLight);
        }

        loadModel() {
            const loader = new GLTFLoader();
            const baseUrl = window.BASE_URL || '/';
            
            loader.load(baseUrl + 'app/views/uploads/robot.glb', (gltf) => {
                const model = gltf.scene;
                
                // Center model
                const box = new THREE.Box3().setFromObject(model);
                const center = box.getCenter(new THREE.Vector3());
                model.position.sub(center);

                model.position.x += 4;
                model.position.y -= 1;

                // Scale model
                const size = box.getSize(new THREE.Vector3());
                const maxDim = Math.max(size.x, size.y, size.z);
                const scale = 5.5 / maxDim;
                model.scale.multiplyScalar(scale);

                // Add model to scene
                this.scene.add(model);

                if (gltf.animations && gltf.animations.length > 0) {
                    this.mixer = new THREE.AnimationMixer(model);
                    gltf.animations.forEach((clip) => {
                        const action = this.mixer.clipAction(clip);
                        action.play();
                    });
                }
            }, undefined, (error) => {
                console.error('Error loading model:', error);
            });
        }

        animate() {
            requestAnimationFrame(() => this.animate());

            if (this.mixer) {
                const delta = this.clock.getDelta();
                this.mixer.update(delta);
            }

            this.controls.update();
            this.renderer.render(this.scene, this.camera);
        }

        onWindowResize() {
            this.camera.aspect = window.innerWidth / window.innerHeight;
            this.camera.updateProjectionMatrix();
            this.renderer.setSize(window.innerWidth, window.innerHeight);
        }
    }

    new ModelViewer();
}


