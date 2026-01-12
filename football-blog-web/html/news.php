<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>News</title>
    <link rel="stylesheet" href="css/news.css">
</head>
<body>
<div class="dashboard-content">

    <div class="viss">
        <div class="virsraksts">
            <img src="images/footy.png"  width="130" height="80">
        </div>

        <!-- SEARCH BAR -->
        <div class="search">
          <div class="search-function">
            <input
                type="text"
                id="searchInput"
                placeholder="Search pages..."
                onkeyup="searchPages()"
            />
            <div id="searchResults" class="search-results"></div>
            </div>
        </div>

        <!-- LOGOUT -->
        <div class="logout">
              <form method="GET" action="">
                  <button type="submit" name="logout">
                    <!-- SVG Logout Icon -->
                      <div class="logout-container">
                        <svg class="logout-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="50" height="50">
                          <!-- Circle outline with gradient stroke -->
                          <defs>
                            <linearGradient id="grad-light" x1="0%" y1="0%" x2="100%" y2="100%">
                              <stop offset="0%" stop-color="#4facfe"/>
                              <stop offset="100%" stop-color="#00f2fe"/>
                            </linearGradient>
                            <linearGradient id="grad-dark" x1="0%" y1="0%" x2="100%" y2="100%">
                              <stop offset="0%" stop-color="#6a11cb"/>
                              <stop offset="100%" stop-color="#2575fc"/>
                            </linearGradient>
                          </defs>

                          <!-- Outer circle -->
                          <circle cx="12" cy="12" r="11" fill="none" stroke="url(#grad-light)" stroke-width="2"/>

                          <!-- Door rectangle -->
                          <rect x="9" y="8" width="6" height="8" fill="currentColor"/>

                          <!-- Arrow pointing right -->
                          <path d="M13 12h5m-2-2l2 2-2 2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                      </div>

                  </button>
              </form>
          </div>
    </div>

    <!-- NAVIGATION -->
    <div class="pages">
        <div class="home">
            <button onclick="document.location='home.php'" class="button-22" role="button">Home</button>
        </div>
        <button onclick="document.location='news.php'" class="button-22" role="button">News</button>

        <div class="top-leagues">
            <div class="dropdown">
                <button onclick="toggleDropdown()" class="dropbtn">Top Leagues</button>
                <div id="myDropdown" class="dropdown-content">
                    <a href="premier.php">Premier league</a>
                    <a href="bundesliga.php">Bundesliga</a>
                    <a href="ligue.php">Ligue 1</a>
                    <a href="seria.php">Serie A</a>
                    <a href="laliga.php">LaLiga</a>
                </div>
            </div>
        </div>
        <div class="about">
        <button onclick="document.location='about.php'" class="button-22" role="button">About</button>
            </div>
        <div class="contacts">
        <button onclick="document.location='contacts.php'" class="button-22" role="button">Contacts</button>
            </div>
    </div>

    <!-- NEWS CONTAINER -->
    <div class="container">
        <h1>⚽ Latest Football News</h1>
        <div id="news1"></div>
        <div id="loading" class="loading">Loading news…</div>
    </div>

</div>

<!-- LINK ALL JS -->
<script src="js/function.js"></script>
<script>
  // Fetch news on page load
  fetchNews();
  setInterval(fetchNews, 60000); // refresh every 60s
</script>

</body>
</html>