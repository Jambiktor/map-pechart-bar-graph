<?php

class Index {
    private $username = 'root';
    private $password = '';
    public $pdo = null;
    private $crime;
    private $date;
    private $location;
    private $latitude;
    private $longitude;

    public function __construct($crime = null, $date = null, $location = null, $latitude = null, $longitude = null) {
        $this->crime = $crime;
        $this->date = $date;
        $this->location = $location;
        $this->latitude = $latitude;
        $this->longitude = $longitude;
    }

    public function con() {
        try {
            $this->pdo = new PDO('mysql:host=localhost;dbname=chart_db', $this->username, $this->password);
        } catch (PDOException $e) {
            die($e->getMessage());
        }

        return $this->pdo;
    }

    public function getMarkers() {
        $con = $this->con();
        $sql = "SELECT crime, location, latitude, longitude FROM piechart_tbl";
        $data = $con->prepare($sql);
        $data->execute();

        return $data->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insertCrime() {
        $con = $this->con();
        $sql = "INSERT INTO piechart_tbl (crime, date, location, latitude, longitude) VALUES (:crime, :date, :location, :latitude, :longitude)";
        $data = $con->prepare($sql);
        $data->bindParam(':crime', $this->crime);
        $data->bindParam(':date', $this->date);
        $data->bindParam(':location', $this->location);
        $data->bindParam(':latitude', $this->latitude);
        $data->bindParam(':longitude', $this->longitude);

        return $data->execute();
    }

    public function getCrimes() {
        $con = $this->con();
        $sql = "SELECT crime_name FROM crimes";
        $data = $con->prepare($sql);
        $data->execute();

        $result = $data->fetchAll(PDO::FETCH_ASSOC);
        if (count($result) > 0) {
            foreach ($result as $row) {
                echo "<option>" . htmlspecialchars($row["crime_name"]) . "</option>";
            }
        } else {
            echo "<option value=''>No Items Found</option>";
        }

        return $data;
    }

    public function getCity() {
        $con = $this->con();
        $sql = "SELECT city FROM tbl_city";
        $data = $con->prepare($sql);
        $data->execute();

        $result = $data->fetchAll(PDO::FETCH_ASSOC);
        if (count($result) > 0) {
            foreach ($result as $row) {
                echo "<option>" . htmlspecialchars($row["city"]) . "</option>";
            }
        } else {
            echo "<option value=''>No Items Found</option>";
        }

        return $data;
    }

    public function viewChart() {
        $con = $this->con();
        $sql = "SELECT crime, COUNT(*) AS CrimeCount FROM piechart_tbl GROUP BY crime";
        $data = $con->prepare($sql);
        $data->execute();

        $data_array = array();
        $total_count = 0;

        while ($row = $data->fetch(PDO::FETCH_ASSOC)) {
            $total_count += $row["CrimeCount"];
        }

        $data->execute();

        while ($row = $data->fetch(PDO::FETCH_ASSOC)) {
            $percentage = ($row["CrimeCount"] / $total_count) * 100;
            $data_array[] = array($row["crime"], round($percentage, 2));
        }

        $data_json = json_encode($data_array);
        return $data_json;
    }

    public function insertCrimes() {
        if (!empty($_GET['crime']) && !empty($_GET['date']) && !empty($_GET['location']) && !empty($_GET['lat']) && !empty($_GET['lng'])) {
            $index = new Index($_GET['crime'], $_GET['date'], $_GET['location'], $_GET['lat'], $_GET['lng']);
            if ($index->insertCrime()) {
                echo "Saved Successfully";
                header("Location: index.php");
                exit();
            } else {
                echo "Failed!";
            }
        }
    }

    public function viewCharts() {
        return $this->viewChart();
    }

    public function getCrimeDropdown() {
        $this->getCrimes();
    }

    public function getCityDropdown() {
        $this->getCity();
    }
}

$index = new Index();

if ($_SERVER["REQUEST_METHOD"] === 'GET') {
    $index->insertCrimes();
}

$data_json = $index->viewCharts();
$markers = $index->getMarkers();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.maptiler.com/maptiler-sdk-js/v2.0.3/maptiler-sdk.umd.js"></script>
    <link href="https://cdn.maptiler.com/maptiler-sdk-js/v2.0.3/maptiler-sdk.css" rel="stylesheet" />
    <script src="https://cdn.maptiler.com/leaflet-maptilersdk/v2.0.0/leaflet-maptilersdk.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <script src="https://cdn.maptiler.com/maptiler-sdk-js/v2.0.3/maptiler-sdk.umd.min.js"></script>
    <link href="https://cdn.maptiler.com/maptiler-sdk-js/v2.0.3/maptiler-sdk.css" rel="stylesheet" />
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>

    <script type="text/javascript">
    google.charts.load('current', {
        'packages': ['corechart']
    });
    google.charts.setOnLoadCallback(drawChart);

    function drawChart() {
        var data = google.visualization.arrayToDataTable([
            ['Crimes', 'Count'],
            <?php 
                if(isset($data_json)){
                    $data_array = json_decode($data_json, true);
                    foreach ($data_array as $crime) {
                        echo "['" . $crime[0] . "', ". $crime[1] . "],";
                    }
                }
            ?>
        ]);

        var options = {
            title: 'Crime Reports'
        };

        var chart = new google.visualization.PieChart(document.getElementById('piechart'));
        chart.draw(data, options);
    }
    </script>

    <script type="text/javascript">
    google.charts.load('current', {
        'packages': ['corechart', 'bar']
    });
    google.charts.setOnLoadCallback(drawStuff);

    function drawStuff() {
        var data = google.visualization.arrayToDataTable([
            ['Crime', 'Percentage'],
            <?php
            if(isset($data_json)){
                $data_array = json_decode($data_json, true);
                foreach ($data_array as $crime) {
                    echo "['" . $crime[0] . "', ". $crime[1] . "],";
                }
            }
        ?>
        ]);

        var options = {
            title: 'Crime Reports',
            width: 900,
            legend: {
                position: 'none'
            },
            chart: {
                title: 'Bar Graph Crime Reports',
                subtitle: 'crime by percentage'
            },
            bars: 'horizontal',
            axes: {
                x: {
                    0: {
                        side: 'top',
                        label: 'Percentage'
                    }
                }
            },
            bar: {
                groupWidth: "40%"
            }
        };

        var chart = new google.visualization.BarChart(document.getElementById('dual_x_div'));
        chart.draw(data, options);
    }
    </script>

</head>

<body>
    <nav class="navbar navbar-dark bg-dark shadow">
        <span class="navbar-brand mb-0 h1">Chart and Graph</span>
    </nav>

    <div class="container mt-3">
        <div class="row">
            <div class="col-sm-3">
                <form action="" method="GET">
                    <div class="form-group">
                        <label for="task">Insert Crime:</label>
                        <select class="form-control mt-0" name="crime" id="crime">
                            <?php $index->getCrimeDropdown();?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="task">Insert Date:</label>
                        <input type="date" name="date" class="form-control mt-0" placeholder="Insert Date" required>
                    </div>
                    <div class="form-group">
                        <label for="task">Insert Location:</label>
                        <select class="form-control mt-0" name="location" id="location">
                            <?php $index->getCityDropdown(); ?>
                        </select>
                        <input type="hidden" name="lat" id="lat">
                        <input type="hidden" name="lng" id="lng">
                    </div>
                    <input type="submit" class="btn btn-success form-control mt-2" value="Add Task">
                </form>
            </div>

            <div class="col-md-6">
                <div id="map" class="form-control" style="height: 570px; width: 870px"></div>
                <div id="piechart" class="form-control mt-2" style="height: 500px; width: 870px"></div>
                <div id="dual_x_div" class="form-control" style="height: 500px; width: 870px"></div>
            </div>
        </div>
    </div>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous">
    </script>

    <script>
    const key = 'sdMXTvTD6NiZI2F6n4sf';
    const map = L.map('map').setView([14.4791, 120.8970], 10);
    const mtLayer = L.maptilerLayer({
        apiKey: key,
        style: "basic-v2",
    }).addTo(map);

    <?php foreach ($markers as $marker): ?>
    L.marker([<?= $marker['latitude'] ?>, <?= $marker['longitude'] ?>]).addTo(map).bindPopup(
        "<div style='text-align: center;'><b><?= htmlspecialchars($marker['crime']) ?></b><br><b><?= htmlspecialchars($marker['location']) ?>, Cavite</b></div>"
    );
    <?php endforeach; ?>
    </script>

    <script type="text/javascript">
    async function getCoordinates(address) {
        const apiKey = 'sdMXTvTD6NiZI2F6n4sf';
        const url =
            `https://api.maptiler.com/geocoding/${encodeURIComponent(address + ", Cavite")}.json?key=${apiKey}`;

        try {
            const response = await fetch(url);
            console.log(url);
            if (!response.ok) {
                if (response.status === 401) {
                    throw new Error('Invalid API key');
                } else {
                    throw new Error(`Error: ${response.status} ${response.statusText}`);
                }
            }

            const data = await response.json();
            console.log(data);
            console.log('API RESPONSE:', data);

            if (data.features && data.features.length > 0) {
                const [longitude, latitude] = data.features[0].geometry.coordinates;
                return {
                    latitude,
                    longitude
                };
            } else {
                throw new Error('No results found');
            }
        } catch (error) {
            console.error(error);
            alert(error.message);
            return null;
        }
    }

    document.getElementById('location').addEventListener('change', async (event) => {
        const address = event.target.value;

        if (address) {
            const coords = await getCoordinates(address);
            console.log(coords);
            if (coords) {
                document.getElementById('lat').value = coords.latitude;
                document.getElementById('lng').value = coords.longitude;
            } else {
                document.getElementById('lat').value = 'N/A';
                document.getElementById('lng').value = 'N/A';
            }
        } else {
            document.getElementById('lat').value = '';
            document.getElementById('lng').value = '';
        }
    });
    </script>
</body>

</html>