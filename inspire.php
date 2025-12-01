<?php
// --- Configuration ---
$num_palettes = 8;
$num_words = 12;

$word_list = [
    // Nouns
    'Dream', 'Ocean', 'Forest', 'City', 'Mountain', 'River', 'Star', 'Moon', 'Sun', 'Cloud', 'Shadow', 'Light', 'Silence', 'Music', 'Chaos', 'Order', 'Memory', 'Future', 'Past', 'Illusion', 'Reality', 'Echo', 'Whisper', 'Growth', 'Decay', 'Void', 'Mirage', 'Nebula', 'Crystal', 'Machine', 'Glitch', 'Spark', 'Horizon',
    // Verbs
    'Explore', 'Create', 'Imagine', 'Wander', 'Discover', 'Transform', 'Evolve', 'Connect', 'Reflect', 'Construct', 'Deconstruct', 'Listen', 'Ascend', 'Descend', 'Flow', 'Freeze', 'Ignite', 'Fade', 'Glow', 'Shift', 'Merge', 'Split', 'Build', 'Shatter', 'Pulse', 'Drift',
    // Adjectives
    'Vibrant', 'Muted', 'Chaotic', 'Serene', 'Ancient', 'Futuristic', 'Ephemeral', 'Eternal', 'Luminous', 'Somber', 'Abstract', 'Geometric', 'Organic', 'Fluid', 'Rigid', 'Transparent', 'Opaque', 'Heavy', 'Light', 'Misty', 'Sharp', 'Blurred', 'Surreal', 'Electric', 'Nostalgic', 'Hollow', 'Kinetic',
    // Concepts
    'Symmetry', 'Asymmetry', 'Harmony', 'Dissonance', 'Entropy', 'Gravity', 'Time', 'Space', 'Emotion', 'Logic', 'Solitude', 'Connection', 'Loss', 'Discovery', 'Liminal', 'Zenith', 'Nadir'
];

// --- Generation Logic ---

function generateRandomHex() {
    return sprintf("#%02x%02x%02x", mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
}

// Generate Palettes
$palettes = [];
for ($i = 0; $i < $num_palettes; $i++) {
    $angle = mt_rand(0, 360);
    $palettes[] = [
        'angle' => $angle,
        'start' => generateRandomHex(),
        'end' => generateRandomHex(),
    ];
}

// Generate Words
shuffle($word_list);
$selected_words = array_slice($word_list, 0, $num_words);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inspiration Generator</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background-color: #121212;
            color: #e0e0e0;
            margin: 0;
            padding: 2em;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            text-align: center;
            margin-bottom: 3em;
            border-bottom: 1px solid #333;
            padding-bottom: 2em;
        }
        h1 {
            font-weight: 300;
            font-size: 3em;
            letter-spacing: 4px;
            margin: 0 0 0.5em 0;
            color: #fff;
        }
        h2 {
            font-weight: 300;
            font-size: 1.5em;
            margin-bottom: 1em;
            color: #aaa;
            border-left: 4px solid #e27979;
            padding-left: 15px;
        }
        .regenerate-btn {
            background: #e27979;
            color: white;
            border: none;
            padding: 12px 30px;
            font-size: 1em;
            border-radius: 30px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(226, 121, 121, 0.3);
        }
        .regenerate-btn:hover {
            background: #d66a6a;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(226, 121, 121, 0.4);
        }
        
        /* Grid Layouts */
        .grid-container {
            display: grid;
            gap: 25px;
            margin-bottom: 4em;
        }
        
        /* Palettes */
        .palettes-grid {
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        }
        .palette {
            height: 180px;
            border-radius: 16px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            position: relative;
            transition: transform 0.3s ease;
        }
        .palette:hover {
            transform: scale(1.02);
        }
        .palette-info {
            background: rgba(0, 0, 0, 0.25);
            padding: 6px 12px;
            border-radius: 20px;
            font-family: monospace;
            font-size: 0.85em;
            color: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(4px);
            margin: 2px;
            text-shadow: 0 1px 2px rgba(0,0,0,0.3);
        }

        /* Words */
        .words-grid {
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        }
        .word-card {
            background: #1e1e1e;
            padding: 2em 1em;
            border-radius: 12px;
            text-align: center;
            font-size: 1.3em;
            font-weight: 500;
            color: #ccc;
            border: 1px solid #333;
            transition: all 0.3s ease;
            cursor: default;
        }
        .word-card:hover {
            background: #2a2a2a;
            color: #fff;
            border-color: #555;
            transform: translateY(-3px);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>INSPIRATION</h1>
            <p style="color: #777; margin-bottom: 20px;">Curated chaos for your next creation.</p>
            <button class="regenerate-btn" onclick="window.location.reload()">Generate New</button>
        </div>

        <h2>Color Moods</h2>
        <div class="grid-container palettes-grid">
            <?php foreach ($palettes as $palette): ?>
                <div class="palette" style="background: linear-gradient(<?php echo $palette['angle']; ?>deg, <?php echo $palette['start']; ?>, <?php echo $palette['end']; ?>);">
                    <div class="palette-info"><?php echo strtoupper($palette['start']); ?></div>
                    <div class="palette-info"><?php echo strtoupper($palette['end']); ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <h2>Concepts & Keywords</h2>
        <div class="grid-container words-grid">
            <?php foreach ($selected_words as $word): ?>
                <div class="word-card">
                    <?php echo htmlspecialchars($word); ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
