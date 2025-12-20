# HumanityReport.com

**[humanityreport.com](https://humanityreport.com)** — An interactive visualization of 300,000 years of human population.

From a few hundred thousand humans scattered across Africa to 8 billion today. The chart animates through the whole thing — ice ages, plagues, the industrial explosion, and that weird vertical line at the end where everything went exponential.

## What it is

A single-page site with:

- An animated canvas chart you can drag around to explore different eras
- Historical markers (catastrophes, tech breakthroughs, civilizations)
- Stats and context about population dynamics
- A section where I asked various AI models to give their unfiltered take on the data

No frameworks, no build tools, no npm. Just HTML, CSS, and vanilla JS.

## Running locally

```bash
php -S localhost:8000
```

Or any static file server. It's just files.

## Building

If you change `styles.css` or `script.js`, run:

```bash
php build.php
```

This minifies the source files and updates cache-busting timestamps.

## Project structure

```
index.html          # The page
styles.css          # Source styles
script.js           # Source JS (chart, animation, interactions)
ai_views.json       # AI commentary data
build.php           # Minifier
```

## Why

I was curious what the full span of human existence looks like when you zoom out far enough that the last 200 years become a near-vertical line. Turns out it's pretty striking.

---

Built by [@robert-schmidt](https://github.com/robert-schmidt) · [View the site](https://humanityreport.com)
