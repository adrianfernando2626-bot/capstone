<?php

if (file_exists('pages/includes/database.php')) {
  include_once('pages/includes/database.php');
}
$sql = 'SELECT phone_number, email FROM userall WHERE role = "Admin"';
$rs  = mysqli_query($db_connection, $sql);
$rw  = mysqli_fetch_array($rs);
$email = $rw["email"] ?? '';
$phone_number = $rw["phone_number"] ?? "";

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.6.0/fonts/remixicon.css" rel="stylesheet" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />

  <link
    href="https://fonts.googleapis.com/css2?family=Albert+Sans:wght@100;200;300;400;500;600;700;800;900&display=swap"
    rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Leaflet -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/styles.css" />
  <title>Landing Page</title>



</head>

<body>
  <header class="main">
    <div class="background-image" id="home"></div>
    <nav>
      <div class="nav-header">
        <div class="nav-logo">
          <a href="#">
            <img src="assets/images/logo70.png" alt="logo" />
          </a>
        </div>
        <div class="nav-menu-btn" id="menu-btn">
          <i class="ri-menu-3-line"></i>
        </div>
      </div>
      <ul class="nav-links" id="nav-links">
        <li><a href="#home">Home</a></li>
        <li><a href="#about">About Us</a></li>
        <li><a href="#contact">Contact Us</a></li>
      </ul>
      <a href="pages/" class="nav-login">Login</a>
    </nav>

    <div class="header-container">
      <h1><span>RentEase</span></h1>
      <p>
        Discover the ultimate platform for hassle-free property rentals. Your
        one-stop solution for finding, listing, and managing rental
        properties.
      </p>
      <div class="header-btn">
        <button class="btn" style="color: white;" onclick="window.location.href='pages/'">
          Get Started
          <i class="ri-arrow-right-long-line"></i>
        </button>
      </div>
      <!-- Social -->
      <ul class="socials">
        <li>
          <a href="https://web.facebook.com/?_rdc=1&_rdr#" target="_blank">
            <i class="ri-facebook-fill"></i>
          </a>
        </li>
        <li>
          <a href="https://x.com/i/flow/login?lang=en-id" target="_blank">
            <i class="ri-twitter-fill"></i>
          </a>
        </li>
        <li>
          <a href="https://www.google.com/" target="_blank">
            <i class="ri-google-fill"></i>
          </a>
        </li>
        <li>
          <a href="https://www.instagram.com/accounts/login/?hl=en" target="_blank">
            <i class="ri-instagram-line"></i>
          </a>
        </li>
      </ul>
    </div>
  </header>

  <!-- Why Choose RentEase -->
  <section class="features-section" id="about">
    <div class="features-container">
      <div class="features-header">
        <h2 class="features-title">Why Choose RentEase?</h2>
        <p class="features-description">
          Hassle-free property management and rental solutions. Our platform
          is designed to simplify your rental experience, whether you're a
          landlord or a tenant.
        </p>
      </div>

      <div class="features-grid">
        <div class="feature-card" style="border-left: 5px solid var(--chart-1)">
          <div class="feature-icon" style="background-color: var(--chart-1)">
            <svg class="icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
          </div>
          <h3 class="feature-card-title">Real-Time Property Insights</h3>
          <p class="feature-card-description">
            Monitor occupancy rates, rental income, and property performance
            with live dashboards. Get instant alerts on vacant units and
            revenue opportunities.
          </p>
        </div>

        <!-- Automated Tenant Management Card -->
        <div class="feature-card" style="border-left: 5px solid var(--chart-2)">
          <div class="feature-icon" style="background-color: var(--chart-2)">
            <svg class="icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
          </div>
          <h3 class="feature-card-title">Automated Tenant Management</h3>
          <p class="feature-card-description">
            Streamline lease processing, automate renewal reminders, and
            manage tenant communications all in one place. Reduce paperwork by
            80%.
          </p>
        </div>

        <!-- Smart Maintenance Tracking Card -->
        <div class="feature-card" style="border-left: 5px solid var(--chart-3)">
          <div class="feature-icon" style="background-color: var(--chart-3)">
            <svg class="icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
          </div>
          <h3 class="feature-card-title">Smart Maintenance Tracking</h3>
          <p class="feature-card-description">
            Track maintenance requests from submission to completion. Manage
            vendor relationships, predict maintenance needs, and reduce
            property downtime.
          </p>
        </div>

        <!-- Financial Analytics Card -->
        <div class="feature-card" style="border-left: 5px solid var(--chart-4)">
          <div class="feature-icon" style="background-color: var(--chart-4)">
            <svg class="icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <h3 class="feature-card-title">Financial Analytics</h3>
          <p class="feature-card-description">
            Optimize rental pricing, track expenses, and maximize ROI with
            comprehensive financial reports. Make data-driven decisions that
            boost profitability.
          </p>
        </div>

        <!-- Document Management Card -->
        <div class="feature-card" style="border-left: 5px solid var(--chart-5)">
          <div class="feature-icon" style="background-color: var(--chart-5)">
            <svg class="icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
          </div>
          <h3 class="feature-card-title">Document Management</h3>
          <p class="feature-card-description">
            Store and organize all property documents in one secure location.
            Digital lease agreements, tenant applications, and maintenance
            records with easy search and sharing.
          </p>
        </div>

        <!-- Payment Status Tracking Card -->
        <div class="feature-card" style="border-left: 5px solid var(--chart-6)">
          <div class="feature-icon" style="background-color: var(--chart-6)">
            <svg class="icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
            </svg>
          </div>
          <h3 class="feature-card-title">Payment Status Tracking</h3>
          <p class="feature-card-description">
            Monitor rent payments online with automatic processing and late fee
            management. Reduce manual time by 65% with automated reminders
            and multiple payment options.
          </p>
        </div>
      </div>
    </div>
  </section>

  <section class="contact-section" id="contact">
    <div class="contact-container">
      <h2 class="contact-title">Get in Touch</h2>
      <p class="contact-description">
        Ready to transform your data into actionable insights? Contact us
        today to learn more about our dashboard solutions and how we can
        help your business grow.
      </p>
      <div class="contact-cards">
        <div class="contact-card">
          <h4 class="contact-card-title">Email Us</h4>
          <p class="contact-card-info"><?php echo $email; ?></p>
        </div>
        <div class="contact-card">
          <h4 class="contact-card-title">Call Us</h4>
          <p class="contact-card-info"><?php echo $phone_number; ?></p>
        </div>
        <div class="contact-card">
          <h4 class="contact-card-title">Visit Us</h4>
          <p class="contact-card-info">
            <button
              class="btn btn-info"
              data-bs-toggle="modal"
              data-bs-target="#viewMapModal">
              <i class="fas fa-map-marker-alt"></i> Location
            </button>
          </p>
        </div>
      </div>
    </div>
  </section>
  </main>


  </div>
  </div>
  </div>
  </div>



  <footer class="footer">
    <div class="footer-container">
      <p class="footer-text">&copy; 2025 Dashboard. All rights reserved by RentEase.</p>
    </div>
  </footer>
  <?php
  // Fetch building list and build the modal content
  $modal = <<<HTML
<!-- ===== Building List Modal ===== -->
<div class="modal fade" id="viewMapModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content shadow-lg rounded-3">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-geo-alt-fill"></i> Choose Building Location
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
HTML;

  $stmt = $db_connection->prepare("SELECT * FROM building");
  $stmt->execute();
  $result = $stmt->get_result();

  while ($row = $result->fetch_assoc()) {
    $buildingId = htmlspecialchars($row['building_id']);
    $buildingName = htmlspecialchars($row['name']);
    $latitude = $row['latitude'] ?: 'null';
    $longitude = $row['longitude'] ?: 'null';
    $address = htmlspecialchars($row['street'] . ', Brgy. ' . $row['barangay'] . ', ' . $row['city'] . ' City, ' . $row['province'] . ', ' . $row['country']);

    $modal .= <<<HTML
        <div class="feature-card p-3 mb-3 shadow-sm border-start border-4 border-info rounded-3">
          <h5 class="feature-card-title mb-1 text-info">{$buildingName}</h5>
          <p class="feature-card-description mb-2">
            <strong>Address:</strong> {$address}
          </p>
          <button 
            class="btn btn-sm btn-info"
            data-bs-toggle="modal"
            data-bs-target="#mapViewModal"
            onclick="showMap('{$buildingName}', {$latitude}, {$longitude})">
            <i class="bi bi-geo-alt"></i> View Location
          </button>
        </div>
HTML;
  }

  $modal .= <<<HTML
      </div>
    </div>
  </div>
</div>

<!-- ===== Reusable Map Modal ===== -->
<div class="modal fade" id="mapViewModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content shadow-lg rounded-3">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-geo-alt-fill"></i> Building Location
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="mapContainer" style="height: 500px; width: 100%; border-radius: 10px;"></div>
      </div>
    </div>
  </div>
</div>

<!-- ===== Map Logic Script ===== -->
<script>
let mapInstance = null;

function showMap(buildingName, lat, lng) {
  const mapContainer = document.getElementById("mapContainer");
  mapContainer.innerHTML = ""; // clear previous content

  if (!lat || !lng) {
    mapContainer.innerHTML = "<p class='text-center text-danger mt-3 fs-5'>No coordinates available for this building.</p>";
    return;
  }

  // Get modal element
  const modal = document.getElementById("mapViewModal");

  // Remove any old event listeners
  modal.removeEventListener("shown.bs.modal", initializeMap);

  // Define the map initialization function
  function initializeMap() {
    // If an old map exists, remove it
    if (mapInstance) {
      mapInstance.remove();
      mapInstance = null;
    }

    // Create a new map
    mapInstance = L.map("mapContainer").setView([lat, lng], 17);
    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
      attribution: "&copy; OpenStreetMap contributors"
    }).addTo(mapInstance);

    // Add marker and popup
    L.marker([lat, lng]).addTo(mapInstance)
      .bindPopup(buildingName)
      .openPopup();

    // Fix layout resizing issues
    setTimeout(() => {
      mapInstance.invalidateSize();
    }, 200);
  }

  // Initialize map when modal is shown
  modal.addEventListener("shown.bs.modal", initializeMap);

  // 🧹 Cleanup map when modal is hidden
  modal.addEventListener("hidden.bs.modal", () => {
    if (mapInstance) {
      mapInstance.remove();
      mapInstance = null;
    }
  });
}
</script>

HTML;

  echo $modal;
  ?>

  <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
  <script src="https://unpkg.com/scrollreveal"></script>
  <script src="assets/js/main.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>


</body>

</html>