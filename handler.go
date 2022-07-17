package kassiscore

import (
	"fmt"
	"github.com/labstack/echo/v4"
	"github.com/rs/zerolog/log"
	"net/http"
)

func HandlerGetMaterials(c echo.Context) error {
	var wres KWRIF
	var KQ KWQIF
	wres.NumFound = 0
	wres.ResponseStatus = "success"

	qs := c.QueryParam("qs")

	log.Debug().Msgf("query params qs:%s", qs)

	//TODO: config値を利用する
	//TODO: ページングなど
	res, err := SolrQuery("http://localhost:8983", "kassiscore", qs)
	if err != nil {
		fmt.Println(err)
		log.Err(err)
		return err
	}

	log.Debug().Msg(fmt.Sprintf("%d", res.Results.NumFound))
	wres.NumFound = res.Results.NumFound

	KQ.Curretpage = 1
	KQ.NumOfPage = 20
	KQ.QueryString = qs
	KQ.QueryMessage = qs
	wres.KQ = KQ
	/*
		fBytes, err := res.Results.Docs.ToBytes()
		if err != nil {
			fmt.Println(err)
		}
		err = json.Unmarshal(fBytes, &wres.Materials)
		if err != nil {
			fmt.Println(err)
		}
	*/
	return c.Render(http.StatusOK, "materials", wres)
	//return c.JSONBlob(http.StatusOK, encodedJSON)
}
