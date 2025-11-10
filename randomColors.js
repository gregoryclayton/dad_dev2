// Function to generate a random linear gradient and apply to various elements
function randomLinearGradient() {
  function randColor() {
    const h = Math.floor(Math.random() * 360);
    const s = 65 + Math.random() * 20;
    const l = 63 + Math.random() * 15;
    return `hsl(${h},${s}%,${l}%)`;
  }
  const color1 = randColor();
  const color2 = randColor();
  return [color1, color2];
}

function setThemeGradient() {
  var titleDiv = document.getElementById('mainTitleContainer');
  var dotDiv = document.getElementById('dot');
  var dotMenuDiv = document.getElementById('dotMenu');
  var entries = document.querySelectorAll('.artist-entry');
  var signInBtns = Array.from(document.querySelectorAll("input[type='submit'][value='sign in'], input[type='submit'][value='Sign In']"));

  var [color1, color2] = randomLinearGradient();
  var grad = `linear-gradient(135deg, ${color1} 60%, ${color2} 100%)`;
  var gradDot = `linear-gradient(135deg, ${color1} 40%, ${color2} 100%)`;

  if (titleDiv) titleDiv.style.backgroundImage = grad;
  if (dotDiv) dotDiv.style.background = gradDot;
  if (dotMenuDiv) dotMenuDiv.style.background = gradDot;

  entries.forEach(function(entry) {
    entry.style.transition = "background 0.7s, box-shadow 0.3s";
    entry.style.background = `linear-gradient(120deg, ${color1} 0%, ${color2} 100%)`;
    entry.style.boxShadow = "0 2px 18px 0 rgba(0,0,0,0.12)";
    entry.style.color = "#fff";
  });

  signInBtns.forEach(function(btn){
    btn.style.background = grad;
    btn.style.transition = "background 0.7s, color 0.2s";
    btn.style.color = "#fff";
    btn.style.fontWeight = "bold";
    btn.style.boxShadow = "0 2px 14px #0002";
    btn.onmouseover = function() { btn.style.filter = "brightness(1.13)"; };
    btn.onmouseleave = function() { btn.style.filter = ""; };
  });
}

function setBWTheme() {
  var titleDiv = document.getElementById('mainTitleContainer');
  var dotDiv = document.getElementById('dot');
  var signOutBtns = document.getElementById('signout');
  var dotMenuDiv = document.getElementById('dotMenu');
  var entries = document.querySelectorAll('.artist-entry');
  var signInBtns = Array.from(document.querySelectorAll("input[type='submit'][value='sign in'], input[type='submit'][value='Sign In']"));

  if (titleDiv) {
    titleDiv.style.backgroundImage = "linear-gradient(135deg, #888888ff 0%, #e0e0e0 100%)";
    titleDiv.style.color = "#222";
  }
  if (dotDiv) {
    dotDiv.style.background = "linear-gradient(135deg, #111 40%, #e0e0e0 100%)";
  }
  if (dotMenuDiv) {
    dotMenuDiv.style.background = "linear-gradient(135deg, #111 40%, #e0e0e0 100%)";
  }
  if (signOutBtns) {
    signOutBtns.style.background = "linear-gradient(135deg, #111 40%, #e0e0e0 100%)";
  }

  entries.forEach(function(entry) {
    entry.style.transition = "background 0.7s, box-shadow 0.3s";
    entry.style.background = "linear-gradient(120deg, #888888 0%, #e0e0e0 100%)";
    entry.style.boxShadow = "0 2px 18px 0 rgba(0,0,0,0.22)";
    entry.style.color = "#111";
  });

  signInBtns.forEach(function(btn){
    btn.style.background = "linear-gradient(135deg, #888888 60%, #e0e0e0 100%)";
    btn.style.color = "white";
    btn.style.fontWeight = "bold";
    btn.style.boxShadow = "0 2px 14px #0002";
    btn.onmouseover = function() { btn.style.filter = "brightness(1.13)"; };
    btn.onmouseleave = function() { btn.style.filter = ""; };
  });
}

function revertOriginalColors() {
    const titleDiv = document.getElementById('mainTitleContainer');
    const dotDiv = document.getElementById('dot');
    const dotMenuDiv = document.getElementById('dotMenu');

    const originalTitleGradient = "linear-gradient(135deg, #e27979 60%, #ed8fd1 100%)";
    const originalDotGradient = "linear-gradient(135deg, #e27979 60%, #ed8fd1 100%)";

    if (titleDiv) titleDiv.style.backgroundImage = originalTitleGradient;
    if (dotDiv) dotDiv.style.background = originalDotGradient;
    if (dotMenuDiv) dotMenuDiv.style.background = "linear-gradient(to bottom right, rgba(226, 121, 121, 0.936), rgba(237, 143, 209, 0.902))";
}

// Attach click handler to the circular dot menu buttons
document.addEventListener('DOMContentLoaded', function() {
  var btn = document.getElementById('changeTitleBgBtn');
  if (btn) {
    btn.addEventListener('click', function(e){
      setThemeGradient();
      e.stopPropagation();
    });
  }
  var bwBtn = document.getElementById('bwThemeBtn');
  if (bwBtn) {
    bwBtn.addEventListener('click', function(e){
      setBWTheme();
      e.stopPropagation();
    });
  }
  var revertBtn = document.getElementById('revertColorsBtn');
  if(revertBtn) {
      revertBtn.addEventListener('click', function(e){
          revertOriginalColors();
          e.stopPropagation();
      });
  }
});
