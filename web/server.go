package main

import (
	"github.com/labstack/echo/v4"
	"github.com/labstack/echo/v4/middleware"
	"github.com/nakamura-akifumi/kassis"
	"html/template"
	"io"
	"net/http"
	"os"
)

func logFormat() string {
	var format string
	format += "time:${time_rfc3339}\t"
	format += "host:${remote_ip}\t"
	format += "forwardedfor:${header:x-forwarded-for}\t"
	format += "req:-\t"
	format += "status:${status}\t"
	format += "method:${method}\t"
	format += "uri:${uri}\t"
	format += "size:${bytes_out}\t"
	format += "referer:${referer}\t"
	format += "ua:${user_agent}\t"
	format += "reqtime_ns:${latency}\t"
	format += "cache:-\t"
	format += "runtime:-\t"
	format += "apptime:-\t"
	format += "vhost:${host}\t"
	format += "reqtime_human:${latency_human}\t"
	format += "x-request-id:${id}\t"
	format += "host:${host}\n"
	return format
}

type Renderer struct {
	template *template.Template
	debug    bool
	location string
}

func NewRenderer(location string, debug bool) *Renderer {
	tpl := new(Renderer)
	tpl.location = location
	tpl.debug = debug

	tpl.ReloadTemplates()

	return tpl
}

func (t *Renderer) ReloadTemplates() {
	t.template = template.Must(template.ParseGlob(t.location))
}

func (t *Renderer) Render(w io.Writer, name string, data interface{}, c echo.Context) error {
	if t.debug {
		t.ReloadTemplates()
	}

	return t.template.ExecuteTemplate(w, name, data)
}

func NewRouter() *echo.Echo {
	e := echo.New()

	e.Renderer = NewRenderer("./views/*.html", true)

	//g := e.Group("/admin")
	//g := e.Group("/api")

	e.Static("/static", "assets")
	e.GET("/", func(c echo.Context) error {
		return c.String(http.StatusOK, "Hello, World!")
	})
	e.File("/", "public/index.html")

	e.GET("/api/materials", kassiscore.HandlerGetApiMaterials)
	e.GET("/materials", kassiscore.HandlerGetMaterials)

	return e
}

func main() {
	router := NewRouter()
	router.HideBanner = true
	router.HidePort = true

	logger := middleware.LoggerWithConfig(middleware.LoggerConfig{
		Format: logFormat(),
		Output: os.Stdout,
	})
	router.Use(logger)
	router.Use(middleware.Recover())
	router.Logger.Fatal(router.Start(":1323"))
}
