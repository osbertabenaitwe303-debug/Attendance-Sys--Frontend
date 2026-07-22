# 🖥️ ST.LUKE Digital Attendance System - Frontend Repository (Vercel)

This repository contains the client-side presentation layer, admin dashboard, teacher portal, and facial recognition web application interfaces.

## 🚀 Deploying to Vercel

1. Push this repository to GitHub: `https://github.com/your-username/ST.LUKE-frontend`
2. Import the repository into your **Vercel** dashboard.
3. Vercel will automatically detect `vercel.json` and deploy your PHP serverless views and frontend assets.
4. Set an Environment Variable in Vercel settings if needed:
   - `NEXT_PUBLIC_API_BASE_URL`: `https://st-luke-backend.onrender.com`

---

## 📂 Repository Structure

- `views/` - User authentication and dashboard views
- `modules/` - Admin and teacher management interface modules
- `includes/` - Common headers, auth guards, and design components
- `js/config.js` - API Base URL provider for Render backend communication
- `vercel.json` - Serverless deployment configuration for Vercel
