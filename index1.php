<?php
/**
 * Classe AppTarification
 * Calcule dynamiquement le tarif en fonction de la distance GPS et de la durée.
 */
class AppTarification {
    private string $titreApp;
    private int $tarifDeBase;      // Prise en charge fixe
    private int $coutParKm;        // Prix variable selon la distance GPS
    private int $coutParMinute;    // Prix variable selon la durée estimée
    private float $latCentre;
    private float $lngCentre;
    private string $messageSysteme = '';
    private string $typeMessage = '';

    public function __construct(
        string $titreApp = "Tarification Dynamique",
        int $tarifDeBase = 500,
        int $coutParKm = 1000,
        int $coutParMinute = 100,
        float $latCentre = -0.1416,
        float $lngCentre = 29.2912
    ) {
        $this->titreApp = $titreApp;
        $this->tarifDeBase = $tarifDeBase;
        $this->coutParKm = $coutParKm;
        $this->coutParMinute = $coutParMinute;
        $this->latCentre = $latCentre;
        $this->lngCentre = $lngCentre;

        $this->traiterFormulaires();
    }

    private function traiterFormulaires(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';

            if ($action === 'connexion') {
                $identifiant = htmlspecialchars(trim($_POST['identifiant'] ?? ''));
                $password = trim($_POST['password'] ?? '');

                if (!empty($identifiant) && !empty($password)) {
                    $this->messageSysteme = "Connexion réussie ! Bienvenue " . $identifiant . ".";
                    $this->typeMessage = "success";
                } else {
                    $this->messageSysteme = "Veuillez remplir tous les champs.";
                    $this->typeMessage = "error";
                }
            } elseif ($action === 'inscription') {
                $nom = htmlspecialchars(trim($_POST['nom'] ?? ''));
                $tel = htmlspecialchars(trim($_POST['tel'] ?? ''));
                $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
                $password = trim($_POST['password'] ?? '');

                if (!empty($nom) && !empty($tel) && $email && !empty($password)) {
                    $this->messageSysteme = "Compte créé pour " . $nom . " ! Connectez-vous.";
                    $this->typeMessage = "success";
                } else {
                    $this->messageSysteme = "Données invalides. Veuillez réessayer.";
                    $this->typeMessage = "error";
                }
            }
        }
    }

    public function rendreUI(): void {
        ?>
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo htmlspecialchars($this->titreApp); ?></title>

            <!-- Leaflet CSS -->
            <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
            <style>
                * {
                    box-sizing: border-box;
                    margin: 0;
                    padding: 0;
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                }

                body {
                    background-color: #f4f6f9;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    min-height: 100vh;
                    padding: 10px;
                }

                .app-card {
                    width: 100%;
                    max-width: 420px;
                    height: 640px;
                    background: #ffffff;
                    border: 2px solid #2c3e50;
                    border-radius: 16px;
                    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
                    overflow: hidden;
                    display: flex;
                    flex-direction: column;
                    position: relative;
                }

                .header {
                    background-color: #2c3e50;
                    color: #ffffff;
                    padding: 14px;
                    text-align: center;
                    font-size: 1.1rem;
                    font-weight: bold;
                    text-transform: uppercase;
                    flex-shrink: 0;
                }

                .content {
                    padding: 15px;
                    flex: 1;
                    overflow-y: auto;
                    display: flex;
                    flex-direction: column;
                    padding-bottom: 65px;
                }

                /* Navigation Bottom */
                .nav-bar-bottom {
                    position: absolute;
                    bottom: 0;
                    left: 0;
                    width: 100%;
                    height: 55px;
                    display: flex;
                    background-color: #ecf0f1;
                    border-top: 2px solid #2c3e50;
                    z-index: 1000;
                }

                .nav-btn {
                    flex: 1;
                    padding: 10px 5px;
                    background: none;
                    border: none;
                    font-size: 0.75rem;
                    font-weight: bold;
                    color: #2c3e50;
                    cursor: pointer;
                    text-transform: uppercase;
                    transition: all 0.2s ease;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    text-align: center;
                }

                .nav-btn:not(:last-child) {
                    border-right: 1px solid #bdc3c7;
                }

                .nav-btn.active {
                    background-color: #2c3e50;
                    color: #ffffff;
                }

                /* Vues */
                .view-section {
                    display: none;
                    flex-direction: column;
                    gap: 10px;
                    height: 100%;
                }

                .view-section.active {
                    display: flex;
                }

                .form-group {
                    display: flex;
                    flex-direction: column;
                    gap: 4px;
                }

                .form-group label {
                    font-size: 0.8rem;
                    font-weight: 700;
                    color: #2c3e50;
                    text-transform: uppercase;
                }

                .form-group input {
                    padding: 9px;
                    border: 2px solid #2c3e50;
                    border-radius: 6px;
                    font-size: 0.85rem;
                    outline: none;
                }

                .input-box {
                    display: flex;
                    gap: 6px;
                }

                .input-box input {
                    flex: 1;
                }

                .btn-gps {
                    background-color: #27ae60;
                    color: white;
                    border: none;
                    padding: 0 12px;
                    border-radius: 6px;
                    cursor: pointer;
                    font-weight: bold;
                    font-size: 0.8rem;
                }

                .btn-gps:hover {
                    background-color: #219150;
                }

                .btn-main {
                    width: 100%;
                    padding: 12px;
                    background-color: #2c3e50;
                    color: white;
                    border: none;
                    border-radius: 6px;
                    font-weight: bold;
                    font-size: 0.9rem;
                    cursor: pointer;
                    text-transform: uppercase;
                    margin-top: 5px;
                }

                .btn-main:hover {
                    background-color: #1a252f;
                }

                #map {
                    width: 100%;
                    height: 150px;
                    border: 2px solid #2c3e50;
                    border-radius: 8px;
                    flex-shrink: 0;
                }

                .result-box {
                    background-color: #f8f9fa;
                    border: 2px dashed #2c3e50;
                    border-radius: 8px;
                    padding: 10px;
                    font-size: 0.82rem;
                    color: #2c3e50;
                    line-height: 1.4;
                }

                .result-box h4 {
                    margin-bottom: 4px;
                    text-align: center;
                    border-bottom: 1px solid #ccc;
                    padding-bottom: 2px;
                }

                .price-tag {
                    color: #27ae60;
                    font-weight: bold;
                    font-size: 1.15rem;
                }

                .status-message {
                    font-size: 0.85rem;
                    text-align: center;
                    padding: 8px;
                    border-radius: 6px;
                    margin-bottom: 10px;
                }

                .status-message.success {
                    background-color: #d4edda;
                    color: #155724;
                    border: 1px solid #c3e6cb;
                }

                .status-message.error {
                    background-color: #f8d7da;
                    color: #721c24;
                    border: 1px solid #f5c6cb;
                }

                .breakdown-list {
                    margin-top: 4px;
                    font-size: 0.75rem;
                    color: #555;
                }
            </style>
        </head>
        <body>

            <div class="app-card">
                <div class="header">
                    <?php echo htmlspecialchars($this->titreApp); ?>
                </div>

                <div class="content">

                    <?php if (!empty($this->messageSysteme)): ?>
                        <div class="status-message <?php echo $this->typeMessage; ?>">
                            <?php echo $this->messageSysteme; ?>
                        </div>
                    <?php endif; ?>

                    <!-- 1. SE CONNECTER (Vue par défaut) -->
                    <div id="view-login" class="view-section active">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="connexion">
                            <div class="form-group">
                                <label>Téléphone ou Email :</label>
                                <input type="text" name="identifiant" required placeholder="Ex: 0999757467">
                            </div>
                            <div class="form-group" style="margin-top: 10px;">
                                <label>Mot de passe :</label>
                                <input type="password" name="password" required placeholder="••••••••">
                            </div>
                            <button type="submit" class="btn-main" style="margin-top: 15px;">Se Connecter</button>
                        </form>
                    </div>

                    <!-- 2. TRAJET (Calcul Dynamique GPS / Temps) -->
                    <div id="view-trajet" class="view-section">
                        <div class="form-group">
                            <label>Point de Départ (GPS) :</label>
                            <div class="input-box">
                                <input type="text" id="inputDepart" placeholder="Cliquez sur GPS ou sur la carte">
                                <button class="btn-gps" onclick="activerGPSDepart()">GPS</button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Destination :</label>
                            <div class="input-box">
                                <input type="text" id="inputDestination" placeholder="Cliquez sur la carte">
                            </div>
                        </div>

                        <div id="map"></div>

                        <button class="btn-main" onclick="calculerTarifDynamique()">Calculer Tarif Dynamique</button>

                        <div id="resultats" class="result-box">
                            <h4>Calculateur Dynamique</h4>
                            Sélectionnez un départ et une destination pour évaluer le coût réel selon les kilomètres et la durée GPS.
                        </div>
                    </div>

                    <!-- 3. CRÉER COMPTE -->
                    <div id="view-register" class="view-section">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="inscription">
                            <div class="form-group">
                                <label>Nom complet :</label>
                                <input type="text" name="nom" required placeholder="Ex: Kasereka Jean">
                            </div>
                            <div class="form-group" style="margin-top: 6px;">
                                <label>Numéro Téléphone :</label>
                                <input type="tel" name="tel" required placeholder="Ex: 0990000000">
                            </div>
                            <div class="form-group" style="margin-top: 6px;">
                                <label>Adresse Email :</label>
                                <input type="email" name="email" required placeholder="Ex: exemple@mail.com">
                            </div>
                            <div class="form-group" style="margin-top: 6px;">
                                <label>Mot de passe :</label>
                                <input type="password" name="password" required placeholder="••••••••">
                            </div>
                            <button type="submit" class="btn-main" style="margin-top: 12px;">Créer mon compte</button>
                        </form>
                    </div>

                </div>

                <!-- ONGLET EN BAS (Se connecter, Trajet, Créer compte) -->
                <div class="nav-bar-bottom">
                    <button class="nav-btn active" id="tab-login" onclick="afficherVue('login')">Se Connecter</button>
                    <button class="nav-btn" id="tab-trajet" onclick="afficherVue('trajet')">Trajet</button>
                    <button class="nav-btn" id="tab-register" onclick="afficherVue('register')">Créer Compte</button>
                </div>
            </div>

            <!-- Leaflet JS -->
            <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

            <script>
                // Variables de tarification transmises depuis PHP
                const LAT_CENTRE = <?php echo $this->latCentre; ?>;
                const LNG_CENTRE = <?php echo $this->lngCentre; ?>;
                const BASE_FARET = <?php echo $this->tarifDeBase; ?>;      // Prise en charge
                const COUT_PAR_KM = <?php echo $this->coutParKm; ?>;        // Variable / KM
                const COUT_PAR_MIN = <?php echo $this->coutParMinute; ?>;    // Variable / Min

                let map, markerDepart, markerArrivee;
                let coordsDepart = null;
                let coordsArrivee = null;

                window.onload = function () {
                    initMap();
                };

                function initMap() {
                    if (map) return;
                    map = L.map('map').setView([LAT_CENTRE, LNG_CENTRE], 13);

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 18,
                        attribution: '© OpenStreetMap'
                    }).addTo(map);

                    map.on('click', function (e) {
                        if (!coordsDepart) {
                            setDepartCoords(e.latlng.lat, e.latlng.lng, "Point carte (Départ)");
                        } else {
                            setDestinationCoords(e.latlng.lat, e.latlng.lng, "Point carte (Arrivée)");
                        }
                    });
                }

                function afficherVue(nomVue) {
                    document.querySelectorAll('.view-section').forEach(el => el.classList.remove('active'));
                    document.querySelectorAll('.nav-btn').forEach(el => el.classList.remove('active'));

                    document.getElementById(`view-${nomVue}`).classList.add('active');
                    document.getElementById(`tab-${nomVue}`).classList.add('active');

                    if (nomVue === 'trajet' && map) {
                        setTimeout(() => map.invalidateSize(), 200);
                    }
                }

                function activerGPSDepart() {
                    if ("geolocation" in navigator) {
                        document.getElementById('resultats').innerHTML = "<i>Recherche du signal GPS...</i>";

                        navigator.geolocation.getCurrentPosition(function (position) {
                            const lat = position.coords.latitude;
                            const lng = position.coords.longitude;
                            setDepartCoords(lat, lng, `GPS (${lat.toFixed(4)}, ${lng.toFixed(4)})`);
                            map.setView([lat, lng], 14);
                            document.getElementById('resultats').innerHTML = "Départ fixé par GPS. Cliquez sur la carte pour la destination.";
                        }, function () {
                            alert("Impossible de récupérer votre position GPS.");
                        }, { enableHighAccuracy: true });
                    } else {
                        alert("Géolocalisation non supportée par votre navigateur.");
                    }
                }

                function setDepartCoords(lat, lng, nom) {
                    coordsDepart = { lat: lat, lng: lng };
                    document.getElementById('inputDepart').value = nom;

                    if (markerDepart) map.removeLayer(markerDepart);
                    markerDepart = L.marker([lat, lng]).addTo(map).bindPopup("<b>Départ</b>").openPopup();
                }

                function setDestinationCoords(lat, lng, nom) {
                    coordsArrivee = { lat: lat, lng: lng };
                    document.getElementById('inputDestination').value = `${nom} (${lat.toFixed(4)}, ${lng.toFixed(4)})`;

                    if (markerArrivee) map.removeLayer(markerArrivee);
                    markerArrivee = L.marker([lat, lng]).addTo(map).bindPopup("<b>Destination</b>").openPopup();
                }

                // CALCULATION DYNAMIQUE : TARIF = BASE + (KM * PRIX_KM) + (MIN * PRIX_MIN)
                async function calculerTarifDynamique() {
                    if (!coordsDepart || !coordsArrivee) {
                        alert("Veuillez définir un point de départ et une destination sur la carte ou via GPS.");
                        return;
                    }

                    document.getElementById('resultats').innerHTML = "<i>Calcul de l'itinéraire et de la durée en cours...</i>";

                    try {
                        // Utilisation du service OSRM pour calculer la distance réelle par route et la durée exacte
                        const url = `https://router.project-osrm.org/route/v1/driving/${coordsDepart.lng},${coordsDepart.lat};${coordsArrivee.lng},${coordsArrivee.lat}?overview=false`;
                        const response = await fetch(url);
                        const data = await response.json();

                        if (data.routes && data.routes.length > 0) {
                            const route = data.routes[0];
                            const distanceKm = route.distance / 1000;
                            const dureeMinutes = route.duration / 60;

                            afficherResultatTarif(distanceKm, dureeMinutes);
                        } else {
                            calculerTarifHaversine();
                        }
                    } catch (e) {
                        calculerTarifHaversine();
                    }
                }

                function afficherResultatTarif(distanceKm, dureeMinutes) {
                    const distFormat = distanceKm.toFixed(2);
                    const minFormat = Math.round(dureeMinutes);

                    // Formule de la tarification dynamique
                    const partDistance = Math.round(distanceKm * COUT_PAR_KM);
                    const partDuree = Math.round(dureeMinutes * COUT_PAR_MIN);
                    const montantTotal = BASE_FARET + partDistance + partDuree;

                    document.getElementById('resultats').innerHTML = `
                        <b>Distance GPS :</b> ${distFormat} Km<br>
                        <b>Durée estimée :</b> ${minFormat} min<br>
                        <div class="breakdown-list">
                            • Prise en charge : ${BASE_FARET} FC<br>
                            • Coût Distance (${COUT_PAR_KM} FC/Km) : ${partDistance} FC<br>
                            • Coût Temps (${COUT_PAR_MIN} FC/min) : ${partDuree} FC
                        </div>
                        <hr style="margin: 4px 0;">
                        <b>Tarif Dynamique Estimé :</b> <span class="price-tag">${montantTotal} FC</span>
                    `;
                }

                function calculerTarifHaversine() {
                    const R = 6371; // Rayon de la Terre en Km
                    const dLat = (coordsArrivee.lat - coordsDepart.lat) * Math.PI / 180;
                    const dLon = (coordsArrivee.lng - coordsDepart.lng) * Math.PI / 180;
                    const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                        Math.cos(coordsDepart.lat * Math.PI / 180) * Math.cos(coordsArrivee.lat * Math.PI / 180) *
                        Math.sin(dLon / 2) * Math.sin(dLon / 2);
                    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

                    const distanceKm = R * c * 1.3; // Majoration moyenne pour la route urbaine
                    const dureeMinutes = distanceKm * 3.5; // Estimation ~ 17 km/h en ville

                    afficherResultatTarif(distanceKm, dureeMinutes);
                }
            </script>
        </body>
        </html>
        <?php
    }
}

// Lancement de l'application
$app = new AppTarification("Tarification Dynamique", 500, 1000, 100);
$app->rendreUI();
?>