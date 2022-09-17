package kassiscore

import (
	"context"
	"fmt"
	"github.com/google/uuid"
	"github.com/nakamura-akifumi/kassis/internal/solr"
	"github.com/rs/zerolog/log"
	"io"
	"io/ioutil"
	"net/http"
	"strings"
)

type SolrMaterial struct {
	Alternative                  string   `json:"alternative"`
	AlternativeTranscription     string   `json:"alternative_transcription"`
	Contents                     []string `json:"contents"`
	Creators                     []string `json:"creators"`
	CreatorsTranscription        []string `json:"creators_transcription"`
	CreatorsAgentIdentifier      []string `json:"creators_agent_identifier"`
	CreatorsLiteral              []string `json:"creators_literal"`
	ID                           string   `json:"id"`
	Identifiers                  []string `json:"identifiers"`
	Materialid                   string   `json:"materialid"`
	Mediatype                    string   `json:"mediatype"`
	Objecttype                   string   `json:"objecttype"`
	Publisher                    string   `json:"publisher"`
	PublisherTranscription       string   `json:"publisher_transcription"`
	PublisherAgentIdentifier     string   `json:"publisher_agent_identifier"`
	PublisherLocation            string   `json:"publisher_location"`
	PublicationPlace             string   `json:"publication_place"`
	SeriesTitle                  string   `json:"series_title"`
	SeriesTitleTranscription     string   `json:"series_title_transcription"`
	Edition                      string   `json:"edition"`
	Title                        string   `json:"title"`
	TitleTranscription           string   `json:"title_transcription"`
	Volume                       string   `json:"volume"`
	VolumeTranscription          string   `json:"volume_transcription"`
	VolumeTitle                  string   `json:"volume_title"`
	VolumeTitleTranscription     string   `json:"volume_title_transcription"`
	Version                      int64    `json:"_version_"`
	Foldername                   string   `json:"foldername"`
	Filename                     string   `json:"filename"`
	Sheetname                    string   `json:"sheetname"`
	SeriesCreatorLiteral         []string `json:"series_creator_literal"`
	Subjects                     []string `json:"subjects"`
	SubjectsTranscription        []string `json:"subjects_transcription"`
	SubjectsResource             []string `json:"subjects_resource"`
	PartInformationTitle         []string `json:"partInformation_title"`
	PartInformationTranscription []string `json:"partInformation_transcription"`
	PartInformationDescription   []string `json:"partInformation_description"`
	PartInformationCreator       []string `json:"partInformation_creator"`
	Descriptions                 []string `json:"descriptions"`
	PublicationDateLiteral       string   `json:"publication_date_literal"`
	IssuedW3cdtf                 string   `json:"issued_w3cdtf"`
	PublicationDateFrom          string   `json:"publication_date_from"`
	PublicationDateTo            string   `json:"publication_date_to"`
	Language                     string   `json:"language"`
	OriginalLanguage             string   `json:"originalLanguage"`
}

func ClearSolrDocument(uriaddress string, corename string) error {

	ctx := context.Background()
	sc, err := solr.NewConnectionAndSingleClient(uriaddress, corename, http.DefaultClient)
	if err != nil {
		log.Fatal().
			Err(err).
			Msgf("Connection error.")
		return err
	}
	_, err = sc.DeleteAll(ctx)
	if err != nil {
		log.Fatal().Err(err)
		return err
	}

	return nil
}

func AddSolrDocument(sc *solr.SingleClient, m SolrMaterial) error {
	if m.ID == "" {
		uuidObj, _ := uuid.NewUUID()
		id := uuidObj.String()
		m.ID = id
	}

	ctx := context.Background()
	params := solr.WriteOptions{Commit: true}
	_, err := sc.Create(ctx, m, &params)
	if err != nil {
		return err
	}
	return nil
}

func UpdateSolrSchema(uri string, corename string, schemafilename string) error {
	bytes, err := ioutil.ReadFile(schemafilename)
	if err != nil {
		log.Err(err)
		return err
	}

	uri = uri + "/solr/" + corename + "/schema"
	req, err := http.NewRequest(http.MethodPost, uri, strings.NewReader(string(bytes)))
	if err != nil {
		log.Err(err)
		return err
	}
	req.Header.Set("Content-Type", "Content-type:application/json")

	fmt.Println("Post schema file")
	client := &http.Client{}
	resp, err := client.Do(req)
	if err != nil {
		log.Err(err)
		return err
	}
	// deferでクローズ処理
	defer func(Body io.ReadCloser) {
		err := Body.Close()
		if err != nil {

		}
	}(resp.Body)
	// Bodyの内容を読み込む
	body, _ := io.ReadAll(resp.Body)

	if resp.StatusCode != 200 {
		log.Error().Msgf("error: response code is %d", resp.StatusCode)
		fmt.Println("Error:")
		fmt.Print(string(body))
		return fmt.Errorf("error: response code is %d", resp.StatusCode)
	}

	fmt.Println("success")
	return nil
}
