<?php require "misc/header.php"; ?>
<title>Binternet</title>
</head>
<body>
    <div class="mainContainer centered">
        <h1 id="bodyHeader">Binternet</h1>
        <p>A privacy respecting Pinterest image search</p>
        <form class="searchContainer " action="search.php" method="get" autocomplete="off">
        <div id="inputWrapper">
          <input type="text" name="q" autofocus/>
          <button class="" type="submit">Search</button>
        </div>

    </div>
<?php require "misc/footer.php"; ?>
