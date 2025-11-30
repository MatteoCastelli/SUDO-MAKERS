<nav class="navbar">
    <ul class="nav-list">
        <li class="nav-item">
            <a href="homepage.php" class="nav-link">Home</a>
        </li>

        <?php if(isset($_SESSION['id_utente'])): ?>
            <li class="nav-item">
                <a href="profile.php" class="nav-link">Profilo</a>
            </li>
            <li class="nav-item">
                <a href="tesseraUtente.php" class="nav-link">Tessera</a>
            </li>
            <li class="nav-item">
                <a href="logout.php" class="nav-link">Esci</a>
            </li>
        <?php else: ?>
            <li class="nav-item">
                <a href="login.php" class="nav-link">Login</a>
            </li>
        <?php endif; ?>


    </ul>
</nav>