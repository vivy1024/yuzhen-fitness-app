"""
标准化用户档案管理MCP服务器 - 完全符合FastMCP 2.0规范
严格遵循FastMCP官方文档最佳实践，简化架构设计

核心特性:
1. 标准异常处理体系 (fastmcp.exceptions)
2. 标准Resource类型 (fastmcp.resources.types)
3. 严格类型注解和Pydantic验证
4. Context集成支持
5. 简化的数据库访问逻辑

运行方式:
python standardized_server.py

端口: 8003 (替换原有错误实现)
"""