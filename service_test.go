package kassiscore

import (
	"github.com/rs/zerolog"
	"github.com/stretchr/testify/assert"
	"os"
	"path/filepath"
	"testing"
)

func TestImportFromISBNFile(t *testing.T) {
	valid_solrserveruri := "http://localhost:8983"
	valid_solrcorename := "kassiscore_test"

	invalid_solrserveruri := "http://localhost:8989"

	err := SolrClearDocument(valid_solrserveruri, valid_solrcorename)
	if err != nil {
		t.Fatal("failed test")
	}

	files := []string{""}
	cnt, err := ImportFromISBNFile(files, invalid_solrserveruri, valid_solrcorename)
	assert.NotEqual(t, err, nil)
	assert.Contains(t, err.Error(), "os: Unable to open file")

	files = []string{"mono"}
	cnt, err = ImportFromISBNFile(files, valid_solrserveruri, valid_solrcorename)
	assert.NotEqual(t, err, nil)
	assert.Contains(t, err.Error(), "os: Unable to open file")

	dir, _ := os.Getwd()
	filepathname := filepath.Join(dir, "testdata", "isbn.txt")
	files = []string{filepathname}
	cnt, err = ImportFromISBNFile(files, valid_solrserveruri, valid_solrcorename)
	assert.Equal(t, err, nil)
	assert.Equal(t, cnt, 6)

	res, err := SolrQuery(valid_solrserveruri, valid_solrcorename, "")
	if err != nil {
		t.Fatal("failed test (query fail")
	}
	if res.Results.NumFound != 6 {
		t.Errorf("failed test (result num found unmatched) Actual numFound=%d", res.Results.NumFound)
	}
	/*
		for _, v := range res.Results.Docs
			v2 := v.(map[string]interface{})["contents"].([]interface{})[0]
			//TODO: ぽっぽ焼き がハイライトさせたい
			assert.Contains(t, v2, "<em>ぽっぽ</em>")
		}

	*/
}

func TestFetchMaterialFromNDLByISBN(t *testing.T) {

	//TODO: NDLサーチのレスポンスはローカル環境から戻す（NDLにつながない）

	//	data, err := FetchMaterialFromNDLByISBN("9784480689108")
	rdf, err := FetchMaterialFromNDLByISBN("9784873119694")
	if err != nil {
		t.Fatal("failed test")
	}
	assert.NotEqual(t, rdf.BibAdminResource.About, "")

	assert.Equal(t, rdf.BibResource.Title.Description.Value, "実用Go言語 : システム開発の現場で知っておきたいアドバイス")
	assert.Equal(t, rdf.BibResource.Title.Description.Transcription, "ジツヨウ ゴーゲンゴ : システム カイハツ ノ ゲンバ デ シッテ オキタイ アドバイス")
	//assert.Equal(t, rdf.BibResource.Alternative.Description.Value, "Practical Go programming")
	//assert.Equal(t, rdf.BibResource.Alternative.Description.Value, "Go言語 : 実用 : システム開発の現場で知っておきたいアドバイス")
	assert.Equal(t, rdf.BibResource.Creator[0].Agent.About, "http://id.ndl.go.jp/auth/entity/00941504")
	assert.Equal(t, rdf.BibResource.Creator[1].Agent.About, "http://id.ndl.go.jp/auth/entity/032205802")
	assert.Equal(t, rdf.BibResource.Creator[2].Agent.About, "http://id.ndl.go.jp/auth/entity/032205806")
	assert.Equal(t, rdf.BibResource.Publisher[0].Agent.Name, "オライリー・ジャパン")
	assert.Equal(t, rdf.BibResource.Publisher[0].Agent.Transcription, "オライリージャパン")
	assert.Equal(t, rdf.BibResource.Publisher[0].Agent.Location, "東京")
	assert.Equal(t, rdf.BibResource.Publisher[1].Agent.Name, "オーム社 (発売)")
	assert.Equal(t, rdf.BibResource.Publisher[1].Agent.Transcription, "オームシャ")
	assert.Equal(t, rdf.BibResource.Publisher[1].Agent.Description, "頒布")
	assert.Equal(t, rdf.BibResource.Publisher[1].Agent.Location, "東京")

	assert.Equal(t, rdf.BibResource.PublicationPlace.Datatype, "http://purl.org/dc/terms/ISO3166")
	assert.Equal(t, rdf.BibResource.PublicationPlace.Text, "JP")
	assert.Equal(t, rdf.BibResource.Date, "2022.4")
	assert.Equal(t, rdf.BibResource.Issued.Datatype, "http://purl.org/dc/terms/W3CDTF")
	assert.Equal(t, rdf.BibResource.Issued.Text, "2022")

	assert.Equal(t, rdf.BibResource.Description[0], "機器種別 : 機器不用")
	assert.Equal(t, rdf.BibResource.Description[1], "キャリア種別 : 冊子")
	assert.Equal(t, rdf.BibResource.Description[2], "表現種別 : テキスト")
	assert.Equal(t, rdf.BibResource.Description[3], "索引あり")
	assert.Equal(t, rdf.BibResource.Description[4], "NDC（9版）はNDC（10版）を自動変換した値である。")

	assert.Equal(t, rdf.BibResource.Subject[0].Description.About, "http://id.ndl.go.jp/auth/ndlsh/00569223")
	assert.Equal(t, rdf.BibResource.Subject[0].Description.Value, "プログラミング (コンピュータ)")
	assert.Equal(t, rdf.BibResource.Subject[1].Resource, "http://id.ndl.go.jp/class/ndc10/007.64")
	assert.Equal(t, rdf.BibResource.Subject[2].Resource, "http://id.ndl.go.jp/class/ndc9/007.64")
	assert.Equal(t, rdf.BibResource.Subject[3].Resource, "http://id.ndl.go.jp/class/ndlc/M159")

	assert.Equal(t, rdf.BibResource.Language[0].Datatype, "http://purl.org/dc/terms/ISO639-2")
	assert.Equal(t, rdf.BibResource.Language[0].Text, "jpn")

	assert.Equal(t, rdf.BibResource.Extent[0], "436p ; 24cm")
	assert.Equal(t, rdf.BibResource.Price[0], "3600円")
	assert.Equal(t, rdf.BibResource.MaterialType[0].Resource, "http://ndl.go.jp/ndltype/Book")
	assert.Equal(t, rdf.BibResource.MaterialType[0].Label, "図書")

}

func TestImportFromFileNCNDLRDF(t *testing.T) {
	solrserveruri := "http://localhost:8983"
	solrcorename := "kassiscore_test"

	err := SolrClearDocument(solrserveruri, solrcorename)
	if err != nil {
		t.Fatal("failed test")
	}

	files := []string{"nonono"}
	err = ImportFromFileNCNDLRDF(files, solrserveruri, solrcorename)
	assert.NotEqual(t, err, nil)
	assert.Contains(t, err.Error(), "os: Unable to open file")

	dir, _ := os.Getwd()
	filepathname := filepath.Join(dir, "testdata", "000462.xml")

	files = []string{filepathname}
	err = ImportFromFileNCNDLRDF(files, solrserveruri, solrcorename)
	assert.Equal(t, err, nil)

}

func TestExtnameToMediaTypeSuccess(t *testing.T) {
	result := ExtnameToMediaType("bar")
	if result != "application/octet-stream" {
		t.Fatal("failed test")
	}

	result = ExtnameToMediaType("xlsx")
	if result != "application/octet-stream" {
		t.Fatal("failed test")
	}

	result = ExtnameToMediaType(".xlsx")
	if result != ContenttypeExcel {
		t.Fatal("failed test")
	}
}

func TestSolrQuery(t *testing.T) {
	solrserveruri := "http://localhost:8983"
	solrcorename := "kassiscore_test"
	tikaserveruri := "http://localhost:9998"

	err := SolrClearDocument(solrserveruri, solrcorename)
	if err != nil {
		t.Fatal("failed test")
	}

	dir, _ := os.Getwd()
	filepathname := filepath.Join(dir, "testdata", "Book1.xlsx")

	files := []string{filepathname}
	err = ImportFromFile(files, tikaserveruri, solrserveruri, solrcorename)
	if err != nil {
		t.Fatal("failed test")
	}

	res, err := SolrQuery(solrserveruri, solrcorename, "ぽっぽ焼き")
	if err != nil {
		t.Fatal("failed test (query fail")
	}
	if res.Results.NumFound != 1 {
		t.Errorf("failed test (result num found unmatched) Actual numFound=%d", res.Results.NumFound)
	}
	for _, v := range res.Highlighting {
		v2 := v.(map[string]interface{})["contents"].([]interface{})[0]
		//TODO: ぽっぽ焼き がハイライトさせたい
		assert.Contains(t, v2, "<em>ぽっぽ</em>")
	}
}

func TestImportFromFile(t *testing.T) {
	solrserveruri := "http://localhost:8983"
	solrcorename := "kassiscore_test"
	tikaserveruri := "http://localhost:9998"
	/*
		userInput := "Y"
		funcDefer, err := mockStdin(t, userInput)
		if err != nil {
			t.Fatal(err)
		}
		defer funcDefer()
	*/
	err := SolrClearDocument(solrserveruri, solrcorename)
	if err != nil {
		t.Fatal("failed test")
	}

	files := []string{"nonono"}

	err = ImportFromFile(files, tikaserveruri, solrserveruri, solrcorename)
	//TODO: errメッセージを確認したい（os: Unable to open file ～）
	if err == nil {
		t.Fatal("failed test")
	}

	dir, _ := os.Getwd()
	filepathname := filepath.Join(dir, "testdata", "Book1.xlsx")

	files = []string{filepathname}
	err = ImportFromFile(files, tikaserveruri, solrserveruri, solrcorename)
	if err != nil {
		t.Fatal("failed test")
	}

	res, err := SolrQuery(solrserveruri, solrcorename, "")
	if err != nil {
		t.Fatal("failed test (query fail")
	}
	if res.Results.NumFound != 8 {
		t.Errorf("failed test (result num found unmatched) Actual numFound=%d", res.Results.NumFound)
	}

	dir, _ = os.Getwd()
	filepathname = filepath.Join(dir, "testdata", "Book1.xlsx")
	targetid := filepathname + "データ1"
	if res.Results.Docs[1].Get("materialid").(string) != targetid {
		t.Errorf("failed test unmatch materialid /expected:%s / actual:%s", targetid, res.Results.Docs[1].Get("materialid").(string))
	}

	err = SolrClearDocument(solrserveruri, solrcorename)
	if err != nil {
		t.Fatal("failed test")
	}

	filepathname = filepath.Join(dir, "testdata", "shinanogawa.pdf")

	files = []string{filepathname}
	err = ImportFromFile(files, tikaserveruri, solrserveruri, solrcorename)
	if err != nil {
		t.Fatal("failed test")
	}
	//TODO: 登録されたデータの内容などを確認したい
	res, err = SolrQuery(solrserveruri, solrcorename, "")
	if err != nil {
		t.Fatal("failed test")
	}
	if res.Results.NumFound != 5 {
		t.Fatal("failed test")
	}

	err = SolrClearDocument(solrserveruri, solrcorename)
	if err != nil {
		return
	}
	filepathname = filepath.Join(dir, "testdata", "shinanogawa.docx")
	files = []string{filepathname}
	err = ImportFromFile(files, tikaserveruri, solrserveruri, solrcorename)
	if err != nil {
		t.Fatal("failed test")
	}
	//TODO: 登録されたデータの内容などを確認したい
	res, err = SolrQuery(solrserveruri, solrcorename, "")
	if err != nil {
		t.Fatal("failed test")
	}
	if res.Results.NumFound != 1 {
		t.Fatal("failed test")
	}

	//txtファイルのテスト
	err = SolrClearDocument(solrserveruri, solrcorename)
	if err != nil {
		return
	}
	filepathname = filepath.Join(dir, "testdata", "shinanogawa.txt")
	files = []string{filepathname}
	err = ImportFromFile(files, tikaserveruri, solrserveruri, solrcorename)
	if err != nil {
		t.Fatal("failed test")
	}
	//TODO: 登録されたデータの内容などを確認したい
	res, err = SolrQuery(solrserveruri, solrcorename, "")
	if err != nil {
		t.Fatal("failed test")
	}
	if res.Results.NumFound != 1 {
		t.Fatal("failed test")
	}

	_ = SolrClearDocument(solrserveruri, solrcorename)

	dir, _ = os.Getwd()
	filepathname1 := filepath.Join(dir, "testdata", "Book1.xlsx")
	filepathname2 := filepath.Join(dir, "testdata", "shinanogawa.pdf")

	files = []string{filepathname1, filepathname2}
	err = ImportFromFile(files, tikaserveruri, solrserveruri, solrcorename)
	if err != nil {
		t.Fatal("failed test")
	}
	res, err = SolrQuery(solrserveruri, solrcorename, "")
	if err != nil {
		t.Fatal("failed test")
	}
	if res.Results.NumFound != 13 {
		t.Fatal("failed test")
	}
}

func TestWebCrawlerInvalidPath(t *testing.T) {

	zerolog.SetGlobalLevel(zerolog.DebugLevel)

	t.Setenv("KASSISCONFIG", "./config.json")

	cfg := LoadConfig()
	result, err := WebCrawler("", cfg)
	assert.Contains(t, result, "ng")
	assert.EqualError(t, err, "Get \"\": unsupported protocol scheme \"\"")

	result, err = WebCrawler("http://www.example.com", cfg)
	assert.Contains(t, result, "ok")
	assert.NoError(t, err)
}

/*
func mockStdin(t *testing.T, dummyInput string) (funcDefer func(), err error) {
	t.Helper()

	oldOsStdin := os.Stdin
	tmpfile, err := ioutil.TempFile(t.TempDir(), t.Name())

	if err != nil {
		return nil, err
	}

	content := []byte(dummyInput)

	if _, err := tmpfile.Write(content); err != nil {
		return nil, err
	}

	if _, err := tmpfile.Seek(0, 0); err != nil {
		return nil, err
	}

	// Set stdin to the temp file
	os.Stdin = tmpfile

	return func() {
		// clean up
		os.Stdin = oldOsStdin
		os.Remove(tmpfile.Name())
	}, nil
}
*/
