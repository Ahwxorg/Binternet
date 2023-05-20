<?php require "misc/header.php"; ?>
<title>Binternet</title>
</head>
<body>
    <div class="mainContainer">
        <h1 id="bodyHeader"><span>P</span>inter<span>n</span>et</h1>
        <form class="searchContainer" action="search.php" method="get" autocomplete="off">
          <div id="inputWrapper"><br><br><br><br>
            <input type="text" name="q" autofocus/>
            <button type="submit">Search</button>
          </div>
        </form>
    </div>
<?php require "misc/footer.php"; ?>
