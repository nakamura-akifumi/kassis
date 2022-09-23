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

	log.Debug().Msg(fmt.Sprintf("%d", len(res)))
	wres.NumFound = len(res)

	KQ.Curretpage = 1
	KQ.NumOfPage = 20
	KQ.QueryString = qs
	KQ.QueryMessage = qs
	wres.KQ = KQ

	// convert to Material struct
	var materials []Material

	for k, v := range res {
		fmt.Println(k, v)
		var cts []string
		for _, v2 := range v.Contents {
			cts = append(cts, v2)
		}

		var m = Material{
			ID:         v.ID,
			MaterialID: v.Materialid,
			ObjectType: "",
			Foldername: "",
			Filename:   "",
			Sheetname:  "",
			Mediatype:  v.Mediatype,
			Contents:   cts,
			Title:      v.Title,
		}
		//v2 := v.(map[string]interface{})["contents"].([]interface{})[0]

		materials = append(materials, m)
	}

	//	if res.Results.Docs[1].Get("materialid").(string) != targetid {
	//		t.Errorf("failed test unmatch materialid /expected:%s / actual:%s", targetid, res.Results.Docs[1].Get("materialid").(string))
	//	}

	wres.Materials = materials

	return c.Render(http.StatusOK, "materials", wres)
	//return c.JSONBlob(http.StatusOK, encodedJSON)
}
