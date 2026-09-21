#!/bin/bash
# Zabiss - Setup Oracle Cloud Free Tier (Ubuntu 22.04 / Oracle Linux)
# VM.Standard.E2.1.Micro (Always Free) ou Ampere A1 (4 OCPU 24GB gratuit)
# Exécuter en root / ubuntu sur le VPS

set -e

echo "=== Zabiss Oracle VPS Setup ==="

# 1. Maj + Docker
if ! command -v docker &> /dev/null; then
  echo "[1] Installation Docker..."
  curl -fsSL https://get.docker.com | sh
  usermod -aG docker $USER || true
fi
if ! docker compose version &> /dev/null; then
  echo "Docker compose plugin manquant - installation..."
  apt-get update && apt-get install -y docker-compose-plugin || yum install -y docker-compose-plugin || true
fi

# 2. Firewall Oracle (iptables + firewalld)
echo "[2] Ouverture ports 80, 443, 8080..."
if command -v firewall-cmd &> /dev/null; then
  firewall-cmd --permanent --add-service=http || true
  firewall-cmd --permanent --add-service=https || true
  firewall-cmd --permanent --add-port=8080/tcp || true
  firewall-cmd --reload || true
fi
# iptables (Oracle Ubuntu)
iptables -I INPUT 6 -m state --state NEW -p tcp --dport 80 -j ACCEPT || true
iptables -I INPUT 6 -m state --state NEW -p tcp --dport 443 -j ACCEPT || true
iptables -I INPUT 6 -m state --state NEW -p tcp --dport 8080 -j ACCEPT || true
netfilter-persistent save 2>/dev/null || iptables-save > /etc/iptables/rules.v4 2>/dev/null || true

# 3. Clone / pull (si déjà présent, git pull)
if [ ! -d "zabiss" ]; then
  echo "[3] Clone repo (remplace URL)..."
  echo "git clone <ton-repo> zabiss && cd zabiss"
else
  echo "[3] Repo déjà présent"
fi

# 4. Lancer
echo "[4] Lancement docker compose..."
# docker compose up --build -d

echo ""
echo "=== Important Oracle Cloud Console ==="
echo "1. Va sur console.oracle.com > Compute > Instance > Ton VPS > VNIC > Subnet > Security List"
echo "   Ajoute Ingress Rules: 0.0.0.0/0 port 80, 443, 8080 (TCP)"
echo "2. Si IP publique change, associe une Reserved Public IP (gratuite si attachée)"
echo "3. Option HTTPS gratuit : sudo apt install certbot && sudo certbot --nginx"
echo ""
echo "Test local: curl http://localhost/api/health"
echo "Puis http://<IP_PUBLIQUE>/"
