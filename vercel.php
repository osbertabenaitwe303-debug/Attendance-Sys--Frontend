{
  "version": 2,
  "builds": [
    {
      "src": "**/*.php",
      "use": "vercel-php"
    },
    {
      "src": "**/*.html",
      "use": "@vercel/static"
    }
  ],
  {
  "functions": {
    "api/**/*.php": { "runtime": "vercel-php@0.6.0" }
  },
  "routes": [
    { "src": "/(.*)", "dest": "/api/$1" }
  ]
}
 
