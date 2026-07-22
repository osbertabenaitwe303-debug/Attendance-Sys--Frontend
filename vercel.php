{
  "version": 2,
  "builds": [
    {
      "src": "index.php",
      "use": "vercel-php"
    },
    {
      "src": "views/*.php",
      "use": "vercel-php"
    }
  ],
  "routes": [
    {
      "src": "/views/(.*)",
      "dest": "/views/$1"
    },
    {
      "src": "/(.*)",
      "dest": "/index.php"
    }
  ]
}
