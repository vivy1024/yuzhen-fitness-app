# Scripts Directory

这个目录包含了项目的自动化脚本和工具。

## 主要脚本

### 启动脚本
- **start_autogen_mcp_system.py**: AutoGen+MCP系统一键启动
- **服务启动脚本.py**: 服务启动管理脚本

### 测试脚本
- **test_mcp_connection_success.py**: MCP连接测试
- **验证MCP连接修复.py**: MCP连接验证
- **test_autogen_api.php**: AutoGen API测试

### 环境设置
- **setup_fastmcp_environment.py**: FastMCP环境设置
- **fix_fastmcp_compatibility.py**: FastMCP兼容性修复

## 使用方法

### 一键启动系统
```bash
cd build_body
python scripts/start_autogen_mcp_system.py
```

### 测试MCP连接
```bash
python scripts/test_mcp_connection_success.py
```

### 设置开发环境
```bash
python scripts/setup_fastmcp_environment.py
```

## 特性

- ✅ 一键启动所有服务
- ✅ 自动健康检查
- ✅ 错误恢复机制
- ✅ 环境兼容性检查
- ✅ 多平台支持