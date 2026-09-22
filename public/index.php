<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=UTF-8');

$nombreHotel = 'Hotel Aurora';
$descripcion = 'Un lugar cómodo para descansar.';
$servicios = [
    ['numero' => '01', 'nombre' => 'Desayuno incluido', 'detalle' => 'Empezá la mañana con café, frutas y pan recién hecho.'],
    ['numero' => '02', 'nombre' => 'Wi-Fi en todo el hotel', 'detalle' => 'Conectate desde tu habitación o nuestros espacios comunes.'],
    ['numero' => '03', 'nombre' => 'Estacionamiento', 'detalle' => 'Un espacio para dejar el auto y disfrutar de tu estadía.'],
    ['numero' => '04', 'nombre' => 'Recepción las 24 horas', 'detalle' => 'Estamos para acompañarte durante tu llegada y tu estadía.'],
];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Conocé Hotel Aurora, sus servicios y ubicación. Sitio académico con información de ejemplo.">
    <meta name="theme-color" content="#172e39">
    <title><?= htmlspecialchars($nombreHotel, ENT_QUOTES, 'UTF-8') ?> | Un lugar para descansar</title>
    <link rel="stylesheet" href="css/styles.css">
    <script src="js/navigation.js" defer></script>
</head>
<body>
    <a class="skip-link" href="#contenido">Saltar al contenido</a>
    <div class="demo-notice">Proyecto académico · Hotel ficticio e información de ejemplo</div>

    <header class="site-header">
        <div class="container header-inner">
            <a class="brand" href="#inicio" aria-label="Hotel Aurora, inicio">
                <span class="brand-monogram" aria-hidden="true">A</span>
                <span>HOTEL <strong>AURORA</strong></span>
            </a>
            <button class="menu-toggle" type="button" aria-controls="navegacion" aria-expanded="false" hidden>Menú</button>
            <nav id="navegacion" class="navigation" aria-label="Navegación principal">
                <a href="#inicio">Inicio</a>
                <a href="#servicios">Servicios</a>
                <a href="habitaciones.php">Habitaciones</a>
                <a class="nav-contact" href="#contacto">Contacto <span aria-hidden="true">↗</span></a>
            </nav>
        </div>
    </header>

    <main id="contenido" tabindex="-1">
        <section id="inicio" class="hero container" aria-labelledby="titulo-hotel">
            <div class="hero-copy">
                <p class="eyebrow">BIENVENIDO A TU PRÓXIMA PAUSA</p>
                <h1 id="titulo-hotel"><?= htmlspecialchars($nombreHotel, ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="hero-tagline"><?= htmlspecialchars($descripcion, ENT_QUOTES, 'UTF-8') ?></p>
                <p class="hero-description">Bajá el ritmo. Disfrutá de espacios tranquilos, una atención cercana y los pequeños detalles que hacen una buena estadía.</p>
                <a class="button" href="habitaciones.php">Conocé nuestras habitaciones <span aria-hidden="true">↓</span></a>
                <p class="hero-location"><span aria-hidden="true">◎</span> Bariloche, Patagonia argentina <span class="example-label">Ubicación de ejemplo</span></p>
            </div>
            <figure class="hero-figure">
                <img src="img/hotel-interior.jpg" width="1280" height="854" alt="Habitación luminosa con cama doble, cortinas blancas y ventanales hacia el exterior." fetchpriority="high">
                <figcaption><span>TIEMPO PARA DESCANSAR</span><span>Fotografía ilustrativa</span></figcaption>
            </figure>
        </section>

        <section id="servicios" class="services-section" aria-labelledby="titulo-servicios">
            <div class="container">
                <div class="section-heading">
                    <div><p class="eyebrow">LOS DETALLES IMPORTAN</p><h2 id="titulo-servicios">Todo para sentirte a gusto.</h2></div>
                    <p>Lo esencial para disfrutar<br class="desktop-break"> desde que llegás.</p>
                </div>
                <ul class="services-grid">
                    <?php foreach ($servicios as $servicio): ?>
                        <li class="service">
                            <span class="service-number" aria-hidden="true"><?= htmlspecialchars($servicio['numero'], ENT_QUOTES, 'UTF-8') ?></span>
                            <h3><?= htmlspecialchars($servicio['nombre'], ENT_QUOTES, 'UTF-8') ?></h3>
                            <p><?= htmlspecialchars($servicio['detalle'], ENT_QUOTES, 'UTF-8') ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>

        <section class="container visit-section" aria-label="Ubicación y contacto">
            <div id="ubicacion" class="location-copy">
                <p class="eyebrow">CERCA DE TU PRÓXIMO PASEO</p>
                <h2>Un punto de partida<br>para descubrir Bariloche.</h2>
                <p>Imaginamos nuestro hotel en un entorno de lagos y montañas, ideal para combinar paseos y descanso.</p>
                <address>Calle del Lago 120<br>San Carlos de Bariloche, Río Negro, Argentina</address>
                <p class="data-note">La dirección y la ubicación son ficticias, elegidas para este proyecto académico.</p>
            </div>
            <div id="contacto" class="contact-panel">
                <p class="eyebrow">ESTAMOS PARA ORIENTARTE</p>
                <h2>Tu estadía empieza<br>con una conversación.</h2>
                <p>Consultas sobre servicios y detalles de la estadía.</p>
                <div class="contact-detail"><span>CORREO DE EJEMPLO</span><p>consultas@hotelaurora.example</p></div>
                <div class="contact-detail"><span>ATENCIÓN PROPUESTA</span><p>Recepción las 24 horas</p></div>
                <p class="contact-note">Correo ficticio. Podés probar el formulario de solicitud con datos de ejemplo. <a href="reservar.php">Solicitar estadía →</a></p>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="container footer-inner">
            <p class="footer-brand"><?= htmlspecialchars($nombreHotel, ENT_QUOTES, 'UTF-8') ?></p>
            <p>Proyecto académico · Todos los datos del hotel son de ejemplo.</p>
            <a href="login.php">Acceso del personal</a>
        </div>
        <div class="container photo-credit">Foto ilustrativa: <a href="https://commons.wikimedia.org/wiki/File:Bedroom_hotel_interior_with_open_door_window._(51536308276).jpg">Nenad Stojkovic</a>, <a href="https://creativecommons.org/licenses/by/2.0/">CC BY 2.0</a>, vía Wikimedia Commons. Imagen recortada en pantalla.</div>
    </footer>
</body>
</html>
