"""
标准化健身动作MCP服务器 - 完全符合FastMCP 2.0规范
严格遵循FastMCP官方文档最佳实践

核心特性:
1. 标准异常处理体系 (fastmcp.exceptions)
2. 标准Resource类型 (fastmcp.resources.types) 
3. 严格类型注解和Pydantic验证
4. Context集成支持
5. 规范的HTTP传输配置

运行方式:
python standardized_server.py

端口: 8001 (替换原有错误实现)
"""