<?php
    include('data.php'); 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forecast</title>
    <link rel="stylesheet" href="style.css">
    <script src="script.js"></script>

</head>

<body>

<div class="container">

    <div class="navigation">
        <div class="nav-logo">
            <a href="/forc" class="nav-logo-link">VTDT SKY</a>
            <p class="nav-logo-text">
                <?php 
                    echo $forecast["city"]["name"] . ", " . $forecast["city"]["country"];
                ?>
            </p>
        </div>

        <div class="nav-info">
            <input class="nav-info-input" type="search" placeholder="Search...">
            <button class="nav-info-button" type="button" id="javaButton" onclick="switchlight()">Light</button>
        </div>

        <div class="nav-links"></div>
    </div>

    <div class="content">

        <div class="pelmenis">
            <p>Current Weather</p>
            <p>Local time: 12:07 PM</p>
        </div>

        <div class="knife">
            <p>Today</p>
            <p>Error loading weather data:</p>

        </div>

        <div class="galds"></div>

        <div class="container-grid">
            <div class="vtd">
                <p>Air Quality</p>
                <p></p>
            </div>

            <div class="vtd5">
                <p>Wind</p>
                <p></p>
            </div>
            <div class="vtd4">
                <p>Humidity</p>
                <p></p>
            </div>

            <div class="vtd3">
                <p>Visibility</p>
                <p></p>  
            </div>

            <div class="vtd2">
                <p>Pressure</p>
                <p></p>
            </div>

            <div class="vtd1">
                <p>Pressure</p>
                <p></p>
            </div>
        </div>

        <p></p>

        <div class="knifenen">
            
            <p>Sun & Moon Summary</p>
          
        <img src="https://forecast-app-vtdt.vercel.app/images/sun.gif" alt="Sun Icon" class="size-12" height="50px">  
        


    </div> 
</div> 







</body>
</html>













