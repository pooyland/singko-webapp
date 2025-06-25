<?php
include 'includes/header.php'; // Includes the top bar, navigation, and opens <main>
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h1 class="text-center mb-4">Contact Us</h1>
            <p class="lead text-center mb-5">Have questions or feedback? We'd love to hear from you!</p>

            <div class="card mb-5">
                <div class="card-body">
                    <h4 class="card-title text-center mb-4">Send Us a Message</h4>
                    <form>
                        <div class="mb-3">
                            <label for="name" class="form-label">Your Name</label>
                            <input type="text" class="form-control" id="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Your Email</label>
                            <input type="email" class="form-control" id="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="subject" class="form-label">Subject</label>
                            <input type="text" class="form-control" id="subject" required>
                        </div>
                        <div class="mb-3">
                            <label for="message" class="form-label">Message</label>
                            <textarea class="form-control" id="message" rows="5" required></textarea>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">Submit Message</button>
                        </div>
                    </form>
                </div>
            </div>

            <h3 class="text-center mb-4 mt-5">Our Founders / Owners</h3>
            <div class="row g-4 justify-content-center">
                <div class="col-md-4 col-sm-6 text-center">
                    <img src="images/founders/gut.jpg" class="rounded-circle mb-3 border border-3 border-primary" alt="Froiland Anthony P. Gutiererz" width="150" height="150">
                    <h5>Froiland Anthony P. Gutiererz</h5>
                    <p class="text-muted">CEO & Co-Founder</p>
                    <a href="https://web.facebook.com/pooyland.gutierrez" class="text-dark me-2"><i class="bx bxl-linkedin-square"></i></a>
                    <a href="https://web.facebook.com/pooyland.gutierrez" class="text-dark"><i class="bx bxl-twitter"></i></a>
                </div>
                <div class="col-md-4 col-sm-6 text-center">
                    <img src="images/founders/cad.jpg" class="rounded-circle mb-3 border border-3 border-primary" alt="Mark James Cademia" width="150" height="150">
                    <h5>Mark James Cademia</h5>
                    <p class="text-muted">CTO & Co-Founder</p>
                    <a href="https://web.facebook.com/markjames.core" class="text-dark me-2"><i class="bx bxl-linkedin-square"></i></a>
                    <a href="https://web.facebook.com/markjames.core" class="text-dark"><i class="bx bxl-github"></i></a>
                </div>
                <div class="col-md-4 col-sm-6 text-center">
                    <img src="images/founders/sit.jpg" class="rounded-circle mb-3 border border-3 border-primary" alt="Dexdee Mark V. Sitoy" width="150" height="150">
                    <h5>Dexdee Mark V. Sitoy</h5>
                    <p class="text-muted">Head of Marketing</p>
                    <a href="https://web.facebook.com/markvaldez.stoy" class="text-dark me-2"><i class="bx bxl-linkedin-square"></i></a>
                    <a href="https://web.facebook.com/markvaldez.stoy" class="text-dark"><i class="bx bxl-instagram-alt"></i></a>
                </div>
                <div class="col-md-4 col-sm-6 text-center">
                    <img src="images/founders/ala.jpg" class="rounded-circle mb-3 border border-3 border-primary" alt="Johngleen Alaba" width="150" height="150">
                    <h5>Johngleen Alaba</h5>
                    <p class="text-muted">Operations Manager</p>
                    <a href="https://web.facebook.com/johnglenn.alaba.37" class="text-dark me-2"><i class="bx bxl-linkedin-square"></i></a>
                    <a href="https://web.facebook.com/johnglenn.alaba.37" class="text-dark"><i class="bx bxl-facebook-square"></i></a>
                </div>
                <div class="col-md-4 col-sm-6 text-center">
                    <img src="images/founders/mels.jpg" class="rounded-circle mb-3 border border-3 border-primary" alt="Melanie R. Wright" width="150" height="150">
                    <h5>Melanie R. Wright</h5>
                    <p class="text-muted">Product Development Lead</p>
                    <a href="https://web.facebook.com/melanie.ranque.wright" class="text-dark me-2"><i class="bx bxl-linkedin-square"></i></a>
                    <a href="https://web.facebook.com/melanie.ranque.wright" class="text-dark"><i class="bx bxl-pinterest"></i></a>
                </div>
            </div>

            <div class="text-center mt-5">
                <p>You can also reach us directly:</p>
                <p><i class="bx bx-envelope me-2"></i> info@singkoecommerce.com</p>
                <p><i class="bx bx-phone me-2"></i> +63 9XX XXX XXXX</p>
                <p><i class="bx bx-map me-2"></i> 123 Main Street, Surigao City, Philippines</p>
            </div>
        </div>
    </div>
</div>

<?php
// Closes <main> tag (opened in header.php)
echo '</main>';
// Add any site-wide footer content here, or include a separate footer.php
?>
<footer class="bg-dark text-white py-4 mt-5">
    <div class="container text-center">
        <p>&copy; <?php echo date('Y'); ?> Singko eCommerce. All rights reserved.</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="js/helpers.js"></script>
<script src="js/config.js"></script>
<script src="js/menu.js"></script>
<script src="js/main.js"></script>
</body>
</html>