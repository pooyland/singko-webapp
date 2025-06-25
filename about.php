<?php
include 'includes/header.php'; // Includes the top bar, navigation, and opens <main>
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h1 class="text-center mb-4">About Singko eCommerce</h1>
            <p class="lead text-center mb-5">Your one-stop shop for amazing products from various sellers.</p>

            <h3 class="mb-3">Our Mission</h3>
            <p>At Singko eCommerce, our mission is to connect buyers with unique and high-quality products from talented sellers. We strive to create a seamless and enjoyable shopping experience, fostering a vibrant community where creativity and commerce thrive.</p>
            <p>We believe in empowering small businesses and individual entrepreneurs by providing them with a robust platform to showcase their goods to a wider audience. Our commitment is to offer diverse product ranges, competitive pricing, and exceptional customer service.</p>

            <h3 class="mb-3 mt-5">Our Values</h3>
            <ul>
                <li><strong>Customer Centricity:</strong> We put our customers first, aiming to exceed their expectations with every interaction.</li>
                <li><strong>Integrity:</strong> We operate with honesty and transparency in all our dealings.</li>
                <li><strong>Innovation:</strong> We continuously seek new ways to improve our platform and services.</li>
                <li><strong>Community:</strong> We foster a supportive environment for both buyers and sellers.</li>
                <li><strong>Quality:</strong> We are committed to offering products that meet high standards of quality and craftsmanship.</li>
            </ul>

            <h3 class="mb-3 mt-5">Our Story</h3>
            <p>Founded in 2023, Singko eCommerce started with a simple idea: to make online shopping more personal and supportive of local talents. What began as a small initiative has grown into a bustling marketplace, thanks to the trust and enthusiasm of our growing community.</p>
            <p>We are constantly evolving, adding new features, and expanding our product categories to meet the ever-changing needs of our users. Join us on this exciting journey as we redefine the online shopping experience!</p>
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

<!-- Bootstrap JS Bundle (placed at the end of body for performance) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<!-- Your custom JS files -->
<script src="js/helpers.js"></script>
<script src="js/config.js"></script>
<script src="js/menu.js"></script>
<script src="js/main.js"></script>
</body>
</html>