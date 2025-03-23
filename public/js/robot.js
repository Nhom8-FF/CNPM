// Using global THREE objects
(function() {
    // Function to initialize the 3D scene
    function initRobotScene() {
        console.log('Initializing robot scene...');
        const canvas = document.getElementById('robot');
        
        if (!canvas) {
            console.error('Robot canvas element not found');
            return;
        }
        
        // Check if THREE.js and dependencies are loaded
        if (typeof THREE === 'undefined' || typeof GLTFLoader === 'undefined' || typeof OrbitControls === 'undefined') {
            console.warn('Still waiting for THREE.js components...');
            displayWaitingMessage(canvas);
            
            // Retry initialization after a delay
            setTimeout(function() {
                if (typeof THREE !== 'undefined' && typeof GLTFLoader !== 'undefined' && typeof OrbitControls !== 'undefined') {
                    initRobotScene();
                } else {
                    console.error('THREE.js dependencies failed to load after waiting');
                    displayErrorMessage(canvas, 'Could not load 3D libraries');
                }
            }, 2000);
            return;
        }
        
        console.log('All THREE.js dependencies loaded correctly');
        
        try {
            // Initialize scene
            const scene = new THREE.Scene();
            
            // Get proper dimensions from container
            const container = canvas.parentElement;
            const width = container ? container.clientWidth : window.innerWidth / 3;
            const height = container ? container.clientHeight : 400;
            
            const camera = new THREE.PerspectiveCamera(45, width / height, 0.1, 1000);
            camera.position.set(0, 2, 8);
    
            const renderer = new THREE.WebGLRenderer({
                canvas: canvas,
                antialias: true,
                alpha: true
            });
            
            renderer.setSize(width, height);
            renderer.setPixelRatio(window.devicePixelRatio);
            renderer.setClearColor(0x000000, 0); // Transparent background
    
            // Add lighting
            const ambientLight = new THREE.AmbientLight(0xffffff, 1.2);
            scene.add(ambientLight);
            
            const directionalLight = new THREE.DirectionalLight(0xffffff, 1);
            directionalLight.position.set(5, 5, 5);
            scene.add(directionalLight);
    
            // Setup orbit controls
            const controls = new OrbitControls(camera, renderer.domElement);
            controls.enableDamping = true;
            controls.dampingFactor = 0.05;
            controls.maxDistance = 15;
            controls.minDistance = 3;
            controls.autoRotate = true;
            controls.autoRotateSpeed = 1.5;
    
            // Animation settings
            const clock = new THREE.Clock();
            let mixer = null;
    
            // Create a spinning cube while model loads
            const rootStyle = getComputedStyle(document.documentElement);
            const primaryColor = rootStyle.getPropertyValue('--primary-color').trim() || '#624af2';
            
            const geometry = new THREE.BoxGeometry(2, 2, 2);
            const material = new THREE.MeshStandardMaterial({ 
                color: new THREE.Color(primaryColor),
                metalness: 0.7,
                roughness: 0.2
            });
            const cube = new THREE.Mesh(geometry, material);
            scene.add(cube);
    
            // Try to load the robot model from multiple paths
            let modelPaths = [];
            
            // Build possible paths for the model
            if (typeof window.BASE_URL !== 'undefined') {
                modelPaths.push(
                    window.BASE_URL + 'public/models/robot.glb',
                    window.BASE_URL + 'uploads/models/robot.glb',
                    window.BASE_URL + 'public/uploads/models/robot.glb'
                );
            }
            
            // Add fallback paths
            modelPaths.push(
                '/public/uploads/models/robot.glb',
                '/uploads/models/robot.glb',
                './public/uploads/models/robot.glb',
                './uploads/models/robot.glb',
                '../public/uploads/models/robot.glb',
                '/WebCourses/public/uploads/models/robot.glb',
                '/WebCourses/uploads/models/robot.glb',
                'D:/PHP/htdocs/WebCourses/public/uploads/models/robot.glb',
                'D:/PHP/htdocs/WebCourses/public/uploads/models/robot.glb'
            );
            
            // Debug message to show all paths we're going to try
            console.log('Will try loading model from these paths:', modelPaths);
            
            // Try to load the model from each path
            function tryLoadModel(pathIndex) {
                if (pathIndex >= modelPaths.length) {
                    console.error('Failed to load robot model from all paths');
                    displayErrorMessage(canvas, 'Could not load 3D model');
                    return;
                }
                
                const modelPath = modelPaths[pathIndex];
                console.log('Attempting to load robot model from:', modelPath);
                
                const loader = new THREE.GLTFLoader();
                
                loader.load(
                    modelPath,
                    function(gltf) {
                        console.log('Robot model loaded successfully from', modelPath);
                        
                        // Remove the temporary cube
                        scene.remove(cube);
                        
                        const model = gltf.scene;
                        
                        try {
                            // Center and position the model
                            const box = new THREE.Box3().setFromObject(model);
                            const center = box.getCenter(new THREE.Vector3());
                            model.position.sub(center);
                            model.position.y -= 1;
                            
                            // Scale the model
                            const size = box.getSize(new THREE.Vector3());
                            const maxDim = Math.max(size.x, size.y, size.z);
                            const scale = 3 / maxDim;
                            model.scale.multiplyScalar(scale);
                            
                            // Add model to scene
                            scene.add(model);
                            
                            // Setup animations if available
                            if (gltf.animations && gltf.animations.length) {
                                mixer = new THREE.AnimationMixer(model);
                                
                                gltf.animations.forEach(function(clip) {
                                    mixer.clipAction(clip).play();
                                });
                                
                                console.log(`Playing ${gltf.animations.length} animations`);
                            }
                        } catch (err) {
                            console.error('Error processing model:', err);
                            displayErrorMessage(canvas, 'Error processing 3D model');
                        }
                    },
                    function(xhr) {
                        if (xhr.lengthComputable) {
                            const percentComplete = Math.round((xhr.loaded / xhr.total) * 100);
                            console.log(`${percentComplete}% loaded`);
                            
                            // Display loading progress on canvas
                            const ctx = canvas.getContext('2d');
                            if (ctx) {
                                ctx.clearRect(0, 0, canvas.width, canvas.height);
                                ctx.fillStyle = 'rgba(0, 0, 0, 0.1)';
                                ctx.fillRect(0, 0, canvas.width, canvas.height);
                                
                                ctx.font = '16px Poppins, sans-serif';
                                ctx.fillStyle = primaryColor;
                                ctx.textAlign = 'center';
                                ctx.fillText(`Loading 3D model: ${percentComplete}%`, canvas.width / 2, canvas.height / 2);
                            }
                        }
                    },
                    function(error) {
                        console.warn(`Failed to load from ${modelPath}:`, error);
                        // Try next path
                        tryLoadModel(pathIndex + 1);
                    }
                );
            }
            
            // Start trying to load from the first path
            tryLoadModel(0);
    
            // Animation loop
            function animate() {
                requestAnimationFrame(animate);
                
                // Animate the cube while model is loading
                if (cube.parent === scene) {
                    cube.rotation.x += 0.01;
                    cube.rotation.y += 0.01;
                }
                
                // Update controls
                controls.update();
                
                // Update animations
                if (mixer) {
                    const delta = clock.getDelta();
                    mixer.update(delta);
                }
                
                // Render scene
                renderer.render(scene, camera);
            }
            
            // Handle window resize
            function onWindowResize() {
                const container = canvas.parentElement;
                const width = container ? container.clientWidth : window.innerWidth / 3;
                const height = container ? container.clientHeight : 400;
                
                camera.aspect = width / height;
                camera.updateProjectionMatrix();
                renderer.setSize(width, height);
            }
            
            window.addEventListener('resize', onWindowResize);
            
            // Start animation loop
            animate();
        } catch (err) {
            console.error('Error initializing 3D scene:', err);
            displayErrorMessage(canvas, 'Failed to initialize 3D scene');
        }
    }
    
    // Display waiting message on canvas
    function displayWaitingMessage(canvas) {
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        if (!ctx) return;
        
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.fillStyle = 'rgba(0, 0, 0, 0.05)';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        
        ctx.font = '16px Poppins, sans-serif';
        ctx.fillStyle = '#624af2';
        ctx.textAlign = 'center';
        ctx.fillText('Loading 3D engine...', canvas.width / 2, canvas.height / 2);
    }
    
    // Display error message on canvas
    function displayErrorMessage(canvas, message) {
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        if (!ctx) return;
        
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.fillStyle = 'rgba(0, 0, 0, 0.05)';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        
        ctx.font = '16px Poppins, sans-serif';
        ctx.fillStyle = '#624af2';
        ctx.textAlign = 'center';
        ctx.fillText(message, canvas.width / 2, canvas.height / 2);
        
        // Draw a robot icon
        ctx.font = '32px "Font Awesome 5 Free"';
        ctx.fillText('\uf544', canvas.width / 2, canvas.height / 2 - 40); // Robot icon from Font Awesome
    }

    // Start initialization when document is ready
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        setTimeout(initRobotScene, 500);
    } else {
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(initRobotScene, 500);
        });
    }
})();