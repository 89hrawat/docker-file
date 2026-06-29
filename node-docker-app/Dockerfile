# ═══════════════════════════════════════════════════════════════
# STAGE 1 — deps
# Install ALL dependencies (prod + dev) needed for building
# ═══════════════════════════════════════════════════════════════
FROM node:20-alpine AS deps

# Set working directory inside the container
WORKDIR /app

# Copy ONLY package files first (layer cache optimization)
# If package.json hasn't changed, Docker reuses this cached layer
COPY package*.json ./

# Install ALL dependencies (including devDependencies for TypeScript build)
RUN npm ci

# ═══════════════════════════════════════════════════════════════
# STAGE 2 — builder
# Compile TypeScript → JavaScript using dev tools from stage 1
# ═══════════════════════════════════════════════════════════════
FROM node:20-alpine AS builder

WORKDIR /app

# Copy node_modules from the deps stage (has devDependencies)
COPY --from=deps /app/node_modules ./node_modules

# Copy all source files into this stage
COPY . .

# Run TypeScript compiler → outputs to /app/dist
RUN npm run build

# ═══════════════════════════════════════════════════════════════
# STAGE 3 — production
# Final lean image: only compiled output + prod dependencies
# No source code, no TypeScript, no devDependencies
# ═══════════════════════════════════════════════════════════════
FROM node:20-alpine AS production

# Set NODE_ENV so Express runs in production mode
ENV NODE_ENV=production
ENV PORT=3000

WORKDIR /app

# Copy only package files (needed to install prod deps)
COPY package*.json ./

# Install ONLY production dependencies (no devDependencies)
RUN npm ci --only=production && npm cache clean --force

# Copy compiled JS from the builder stage (NOT the source TS)
COPY --from=builder /app/dist ./dist

# ── Security: run as non-root ──────────────────────────────────
# Create a dedicated user & group — never run as root in prod
RUN addgroup -S appgroup && adduser -S appuser -G appgroup
USER appuser

# Expose the port the app listens on
EXPOSE 3000

# Health check — Docker will ping this to verify container health
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
  CMD wget -qO- http://localhost:3000/health || exit 1

# Start the compiled app
# Start the compiled app
CMD ["node", "dist/index.js"]
