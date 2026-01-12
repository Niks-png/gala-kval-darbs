<?php
ini_set('display_errors',1);
error_reporting(E_ALL);

$cacheFolder = __DIR__."/cache";
if(!file_exists($cacheFolder)) mkdir($cacheFolder,0755,true);

$rss_url = "https://football-italia.net/feed"; 
$cacheFile = "$cacheFolder/seriea_rss.json";
$cacheTime = 5*60;

function fetchRSS($rss_url){
    $ch = curl_init();
    curl_setopt($ch,CURLOPT_URL,$rss_url);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
    curl_setopt($ch,CURLOPT_FOLLOWLOCATION,true);
    curl_setopt($ch,CURLOPT_USERAGENT,'Mozilla/5.0');
    $response = curl_exec($ch);
    curl_close($ch);
    if(!$response) die("Failed to fetch RSS feed: $rss_url");
    $rss = @simplexml_load_string($response);
    if($rss===false) die("Failed to parse RSS feed: $rss_url");
    return $rss;
}

if(file_exists($cacheFile) && time()-filemtime($cacheFile)<$cacheTime){
    $data = json_decode(file_get_contents($cacheFile),true);
}else{
    $rss = fetchRSS($rss_url);
    $data = [];
    foreach($rss->channel->item as $item){
        $data[] = [
            'title' => (string)$item->title,
            'link' => (string)$item->link,
            'pubDate' => date("d M Y", strtotime($item->pubDate))
        ];
    }
    file_put_contents($cacheFile,json_encode($data));
}

$newsItems = array_slice($data,0,5);

$apiKey = "063f0699e14d434b94fbaa277a38e2f2";
$cacheFile = __DIR__ . "/cache/SA_standings.json";
$cacheTime = 15 * 60;

if (isset($_GET['refresh']) && file_exists($cacheFile)) {
    unlink($cacheFile);
}

if (file_exists($cacheFile) && time() - filemtime($cacheFile) < $cacheTime) {
    $data = json_decode(file_get_contents($cacheFile), true);
} else {
    $ch = curl_init("https://api.football-data.org/v4/competitions/SA/standings");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "X-Auth-Token: $apiKey"
        ]
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    file_put_contents($cacheFile, $response);
    $data = json_decode($response, true);
}

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
    <title>Serie A</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/standings.css">
    <link rel="stylesheet" href="css/premier_news.css">
</head>
<body>
    <div class="dashboard-content">
        <div class="viss">
            <div class="virsraksts">
                 <img src="images/footy.png"  width="130" height="80">
            </div>
          
           <!-- SEARCH -->
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
                <div class="homebutton">
                    <button onclick="document.location='home.php'" class="button-22" role="button">Home</button>
                </div>
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

   <!-- NEWS AND STANDINGS -->
        <div class="bigbox">
            <div class="news-container">
                <h2>🏆 Serie A League Latest News</h2>
                <div class="news-list">
                    <?php if (empty($newsItems)): ?>
                        <p>News not available at the moment.</p>
                    <?php else: ?>
                        <ul>
                            <?php foreach ($newsItems as $item): ?>
                                <li>
                                    <a href="<?= $item['link'] ?>" target="_blank"><?= $item['title'] ?></a>
                                    <small>(<?= $item['pubDate'] ?>)</small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <div class="box2">
                <div class="standings-container">
                    <h2>🏆 Serie A League Standings</h2>
                    <div class="table-wrapper">
                        <table class="standings">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Club</th>
                                    <th>MP</th>
                                    <th>W</th>
                                    <th>D</th>
                                    <th>L</th>
                                    <th>GD</th>
                                    <th>Pts</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data['standings'][0]['table'] as $team): ?>
                                    <tr>
                                        <td class="pos"><?= $team['position'] ?></td>
                                        <td class="team">
                                            <img src="<?= $team['team']['crest'] ?>" alt=""> <?= $team['team']['name'] ?>
                                        </td>
                                        <td><?= $team['playedGames'] ?></td>
                                        <td><?= $team['won'] ?></td>
                                        <td><?= $team['draw'] ?></td>
                                        <td><?= $team['lost'] ?></td>
                                        <td><?= $team['goalDifference'] ?></td>
                                        <td class="points"><?= $team['points'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <a class="refresh" href="?refresh=1">🔄 Refresh</a>
                    </div>
                </div>
            </div>
        </div>
        <footer>
            <p>&copy; 2026 Footy. All rights reserved.</p>
        </footer>
    </div>

  <!-- LINK FUNCTIONS.JS -->
    <script src="js/function.js"></script>
</body>
</html>