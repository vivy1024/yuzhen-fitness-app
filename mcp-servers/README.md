📝 MCP Servers README

# MCP Servers Directory

This directory contains all MCP (Model Context Protocol) servers for the BUILD_BODY fitness application.

## Server List

### Core MCP Servers
- **fitness-exercises-mcp** (Port 8001): 1603 MuscleWiki exercise database
- **user-profile-mcp** (Port 8003): User profile and body data management
- **nutrition-guide-mcp** (Port 8002): Nutrition guidance and meal planning

### Specialized MCP Servers  
- **sports-rehabilitation-mcp** (Port 8004): Sports injury recovery
- **spotr-style-fitness-mcp** (Port 8005): Training plan generation
- **opennutrition-mcp** (Port 8006): International nutrition database

### Monitoring System
- **monitoring**: MCP system health monitoring and recovery

## Quick Start

Start all MCP servers:
```bash
python scripts/start_autogen_mcp_system.py
```

Start individual server:
```bash
python mcp-servers/{server-name}/standardized_server.py
```

## Features

- ✅ FastMCP 2.0 compliance
- ✅ Standard exception handling
- ✅ HTTP/SSE dual transport modes
- ✅ Context-aware services
- ✅ Health monitoring
- ✅ Recovery system