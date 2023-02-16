<footer>
  <div class="footer">
    <p id="footerText">
      |
      <a href="./" target="_blank">Pinternet</a>
      |
      <a href="https://github.com/Ahwxorg/Pinternet/" target="_blank">Source code</a>
      |
      <a href="https://ahwx.org/donate.php" target="_blank">Donate</a>
      |

<?php
if (isset($images)) {
  echo "<br><br>";
  print(count($images). " images found");
}
?>
    </p>
  </div>
</footer>
</body>
</html>
